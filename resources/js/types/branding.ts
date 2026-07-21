export interface Branding {
    app_name: string;
    logo_path: string | null;
    logo_url: string | null;
    primary_color: string;
    accent_color: string;
    running_texts: string[];
    display_theme: 'dark' | 'light';
}
