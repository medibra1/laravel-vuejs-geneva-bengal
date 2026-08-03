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

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
