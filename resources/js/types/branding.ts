export interface RunningText {
    text: string;
    /** Null = automatic, follows the display theme's text colour. */
    color: string | null;
}

export interface Branding {
    app_name: string;
    logo_path: string | null;
    logo_url: string | null;
    primary_color: string;
    accent_color: string;
    running_texts: RunningText[];
    display_theme: 'dark' | 'light';
}
