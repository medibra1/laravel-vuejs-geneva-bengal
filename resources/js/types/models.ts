export interface Color {
    id: number;
    name: string;
    hex_code: string;
}

export interface CatPhoto {
    id: number;
    url: string;
}

export interface Cat {
    id: number;
    slug: string;
    name: string;
    type: "chaton" | "chat" | "reproducteur";
    sex: "male" | "femelle";
    color_id: number;
    second_color_id: number | null;
    color?: Color;
    second_color?: Color | null;
    description: { fr: string; en: string };
    price: number | null;
    birth_date: string | null;
    eye_color: string | null;
    available_at: string | null;
    diet: string | null;
    litter_trained: boolean;
    neutered: boolean;
    status: string;
    photos: CatPhoto[];
}

export interface Owner {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
    city: string | null;
}

export interface LitterCatOption {
    id: number;
    name: string;
}

export interface Litter {
    id: number;
    sire_cat_id: number | null;
    dam_cat_id: number | null;
    sire?: LitterCatOption | null;
    dam?: LitterCatOption | null;
    expected_date: string | null;
    notes: string | null;
    kittens_count?: number;
}

export interface Gallery {
    id: number;
    caption: string | null;
    position: number;
    image_url: string | null;
}

export interface CmsPage {
    id?: number;
    slug?: string;
    menu_group: string | null;
    order: number;
    title: { fr: string; en: string };
    body: { fr: string; en: string } | null;
    meta_title: { fr: string; en: string } | null;
    meta_description: { fr: string; en: string } | null;
    is_published: boolean;
}

export interface MenuPage {
    id: number;
    slug: string;
    menu_group: string;
    order: number;
    title: string;
}

export interface FaqItem {
    id: number;
    question: { fr: string; en: string };
    answer: { fr: string; en: string };
    order: number;
}

export interface Testimonial {
    id: number;
    author_name: string;
    quote: { fr: string; en: string };
    rating: number | null;
    is_published: boolean;
    order: number;
}

export interface SiteSettings {
    social_facebook: string | null;
    social_instagram: string | null;
    social_youtube: string | null;
    social_pinterest: string | null;
    address: string | null;
    deposit_amount: number | null;
    price_range_min: number | null;
    price_range_max: number | null;
    default_seo_title: string | null;
    default_seo_description: string | null;
}

export interface Honeypot {
    enabled: boolean;
    nameFieldName: string;
    unrandomizedNameFieldName: string;
    validFromFieldName: string;
    encryptedValidFrom: string;
    withCsp: boolean;
}

export interface ContactRequest {
    id: number;
    name: string;
    email: string;
    reason: 'adopt' | 'waiting_list' | 'question';
    cat_id: number | null;
    cat?: { id: number; name: string } | null;
    city: string | null;
    message: string;
    status: 'new' | 'processed' | 'archived';
    created_at: string;
}

export interface AdminUser {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    last_login_at: string | null;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
