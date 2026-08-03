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

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
