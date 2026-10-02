/*
 * Globaler Submit-Lock (Ralf, 2026-10-03: "bei Multichange und generell bei allen
 * Speichern-Aktionen"): Ein Formular wird zwischen Absenden und Ende des Vorgangs
 * nicht ein zweites Mal abgeschickt, und seine Absende-Buttons sind in der Zeit
 * deaktiviert (Doppelklick, ungeduldiges Nochmal-Klicken bei langsamer Antwort).
 *
 * Funktioniert ohne Änderung an den einzelnen Formularen:
 * - Normales Absenden (Seite lädt neu): Lock bleibt bis zum Neuladen, Sicherung 15 s
 *   (z. B. Datei-Download, bei dem die Seite stehen bleibt).
 * - Per JavaScript verarbeitetes Absenden (preventDefault + fetch): Lock endet, sobald
 *   die dabei gestarteten Schreib-Anfragen (nicht GET) fertig sind. Wird gar keine
 *   gestartet (Abbruch im Bestätigungsdialog, Validierung), endet er sofort bei
 *   "Abbrechen" bzw. nach 5 s.
 * - Ausnahmen: GET-Formulare (Filter/Suche) und Formulare mit data-no-submit-lock.
 */
const locks = new Map(); // form -> { buttons, started, timer, native }
let pendingWrites = 0;
let openDialogs = 0;

const origFetch = window.fetch.bind(window);

function releaseLock(form) {
    const lock = locks.get(form);
    if (!lock) return;
    clearTimeout(lock.timer);
    lock.buttons.forEach(({ button, opacity, cursor }) => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.style.opacity = opacity;
        button.style.cursor = cursor;
    });
    locks.delete(form);
}

function scheduleFallback(form, ms) {
    const lock = locks.get(form);
    if (!lock) return;
    clearTimeout(lock.timer);
    lock.timer = setTimeout(() => {
        // Solange ein Bestätigungsdialog offen ist oder noch geschrieben wird, nicht lösen.
        if (openDialogs > 0 || pendingWrites > 0) {
            scheduleFallback(form, 1000);
            return;
        }
        releaseLock(form);
    }, ms);
}

function settleIfIdle() {
    if (pendingWrites > 0) return;
    setTimeout(() => {
        if (pendingWrites > 0) return;
        locks.forEach((lock, form) => {
            if (lock.started && !lock.native) releaseLock(form);
        });
    }, 150);
}

window.fetch = function (input, init) {
    const method = String(init?.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
    if (method === 'GET' || method === 'HEAD') {
        return origFetch(input, init);
    }
    pendingWrites++;
    locks.forEach((lock) => { lock.started = true; });
    return origFetch(input, init).finally(() => {
        pendingWrites--;
        settleIfIdle();
    });
};

let dialogWrapped = false;
function wrapConfirmDialog() {
    if (dialogWrapped || typeof window.confirmDialog !== 'function') return;
    dialogWrapped = true;
    const original = window.confirmDialog;
    window.confirmDialog = async (...args) => {
        openDialogs++;
        try {
            const result = await original(...args);
            if (!result) {
                // Abgebrochen: es wird nichts abgeschickt, Buttons sofort wieder frei.
                locks.forEach((lock, form) => { if (!lock.started) releaseLock(form); });
            }
            return result;
        } finally {
            openDialogs--;
        }
    };
}

function submitButtonsFor(form) {
    const own = [...form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])')];
    const external = form.id ? [...document.querySelectorAll(`[form="${form.id}"]`)].filter((el) => el.matches('button, input[type="submit"]') && el.type === 'submit') : [];
    return [...new Set([...own, ...external])];
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-submit-lock')) return;
    const method = String(event.submitter?.formMethod || form.method || 'get').toLowerCase();
    if (method === 'get') return;

    wrapConfirmDialog();

    if (locks.has(form)) {
        // Zweites Absenden, während das erste noch läuft.
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
    }

    const lock = { buttons: [], started: false, timer: null, native: false };
    locks.set(form, lock);

    // Erst NACH dem Event deaktivieren: ein deaktivierter Absende-Button würde beim normalen
    // Absenden Name/Wert des Buttons aus den Formulardaten werfen.
    setTimeout(() => {
        if (locks.get(form) !== lock) return;
        lock.native = !event.defaultPrevented;
        submitButtonsFor(form).forEach((button) => {
            if (button.disabled) return;
            lock.buttons.push({ button, opacity: button.style.opacity, cursor: button.style.cursor });
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.style.opacity = '0.6';
            button.style.cursor = 'wait';
        });
        scheduleFallback(form, lock.native ? 15000 : 5000);
    }, 0);
}, true);

// Seite wird neu geladen oder aus dem Cache zurückgeholt: nichts darf gesperrt bleiben.
window.addEventListener('pageshow', () => {
    [...locks.keys()].forEach(releaseLock);
});
