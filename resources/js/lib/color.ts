// Relative luminance of slate-900 (#0f172a) + 0.05, per WCAG 2.x.
const DARK_TEXT_LUMINANCE = 0.0588;

/**
 * WCAG relative luminance (0 = black, 1 = white) of a 3- or 6-digit hex
 * colour, or null for anything that isn't hex.
 */
export function relativeLuminance(
    color: string | null | undefined,
): number | null {
    const match = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(color?.trim() ?? '');

    if (!match) {
        return null;
    }

    const hex =
        match[1].length === 3
            ? [...match[1]].map((char) => char + char).join('')
            : match[1];

    const [r, g, b] = [0, 2, 4].map((offset) => {
        const channel = parseInt(hex.slice(offset, offset + 2), 16) / 255;

        return channel <= 0.04045
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * Pick dark or white text for a background colour, whichever has the higher
 * WCAG contrast. Admins choose brand and service colours freely, so a fixed
 * white would vanish on light picks like yellow. Non-hex input gets white.
 */
export function readableTextColor(
    background: string | null | undefined,
): '#ffffff' | '#0f172a' {
    const base = relativeLuminance(background);

    if (base === null) {
        return '#ffffff';
    }

    const luminance = base + 0.05;

    return 1.05 / luminance >= luminance / DARK_TEXT_LUMINANCE
        ? '#ffffff'
        : '#0f172a';
}
