/*
 * Sofort erscheinende Tooltips (Ralf, 2026-10-09): Die eingebauten Browser-Tooltips (title-Attribut) brauchen etwa eine Sekunde,
 * bis sie erscheinen - wer mehrere Stellen überfährt, wartet insgesamt zu lange. Statt jede Stelle umzubauen, übernimmt diese
 * Datei den title-Text aller Elemente: Beim Überfahren wird der title kurz entfernt (damit der Browser nichts anzeigt) und der Text
 * sofort in einem kleinen Overlay neben dem Mauszeiger gezeigt; beim Verlassen kommt der title zurück.
 *
 * Opt-out: data-native-title am Element (oder einem Vorfahren) lässt den Browser-Tooltip stehen.
 */
const OFFSET_X = 12;
const OFFSET_Y = 16;

let bubble = null;
let current = null;   // { element, text }

function ensureBubble() {
    if (bubble) return bubble;
    bubble = document.createElement('div');
    bubble.setAttribute('role', 'tooltip');
    bubble.style.cssText = 'position:fixed;z-index:2147483000;pointer-events:none;display:none;max-width:22rem;padding:4px 8px;border-radius:6px;'
        + 'background:#1f2937;color:#fff;font-size:12px;line-height:1.35;white-space:pre-line;box-shadow:0 2px 8px rgba(0,0,0,.25);';
    document.body.appendChild(bubble);

    return bubble;
}

function position(event) {
    if (!bubble || bubble.style.display === 'none') return;
    const margin = 8;
    let left = event.clientX + OFFSET_X;
    let top = event.clientY + OFFSET_Y;
    const width = bubble.offsetWidth;
    const height = bubble.offsetHeight;
    if (left + width + margin > window.innerWidth) left = Math.max(margin, event.clientX - width - OFFSET_X);
    if (top + height + margin > window.innerHeight) top = Math.max(margin, event.clientY - height - OFFSET_Y);
    bubble.style.left = left + 'px';
    bubble.style.top = top + 'px';
}

function hide() {
    if (current) {
        // title nur zurückgeben, wenn nicht inzwischen ein neuer Wert gesetzt wurde (z. B. durch Alpine)
        if (!current.element.hasAttribute('title')) current.element.setAttribute('title', current.text);
        current = null;
    }
    if (bubble) bubble.style.display = 'none';
}

function show(element, text, event) {
    const target = ensureBubble();
    target.textContent = text;
    target.style.display = 'block';
    position(event);
}

document.addEventListener('mouseover', (event) => {
    const element = event.target instanceof Element ? event.target.closest('[title]') : null;
    if (!element || element.closest('[data-native-title]')) {
        if (current && !(event.target instanceof Element && current.element.contains(event.target))) hide();

        return;
    }
    if (current && current.element === element) return;
    hide();
    const text = element.getAttribute('title');
    if (!text || !text.trim()) return;
    element.removeAttribute('title');
    current = { element, text };
    show(element, text, event);
}, true);

document.addEventListener('mousemove', position, true);

document.addEventListener('mouseout', (event) => {
    if (!current) return;
    const to = event.relatedTarget instanceof Node ? event.relatedTarget : null;
    if (!to || !current.element.contains(to)) hide();
}, true);

// Klicken, Tippen, Scrollen und Seitenwechsel beenden den Tooltip
['mousedown', 'keydown', 'scroll', 'blur'].forEach((name) => window.addEventListener(name, hide, true));
document.addEventListener('visibilitychange', hide);
