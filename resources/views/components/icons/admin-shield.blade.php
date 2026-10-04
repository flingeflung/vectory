{{-- Vietto-Symbol symb1.gif (gelbes Schild mit A) als SVG; Ralf, 2026-10-04: kennzeichnet "nur für Admins sichtbar". --}}
<svg {{ $attributes->merge(['class' => 'inline-block h-3.5 w-3.5 align-text-bottom']) }} viewBox="0 0 14 15" aria-hidden="true">
    <defs>
        <linearGradient id="admin-shield-fill" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#fffbe9" />
            <stop offset="1" stop-color="#ffcf0d" />
        </linearGradient>
    </defs>
    <path d="M1.5 1.5 H12.5 V9 Q12.5 11.8 7 14 Q1.5 11.8 1.5 9 Z" fill="url(#admin-shield-fill)" stroke="#666" stroke-width="1" stroke-linejoin="round" />
    <path d="M4.5 10 L7 4.2 L9.5 10 M5.5 8.3 H8.5" fill="none" stroke="#333" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
</svg>
