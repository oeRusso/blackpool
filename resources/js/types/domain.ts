export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

export type Option<V = string> = {
    value: V;
    label: string;
};

export type LessonStatus = 'draft' | 'published';

export type Level = {
    id: number;
    code: string;
    name: string;
    description?: string | null;
};

export type Category = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    lessons_count?: number;
};

export type AdminLessonRow = {
    id: number;
    title: string;
    slug: string;
    level: string;
    categories: string[];
    status: LessonStatus;
    is_free: boolean;
    published_at: string | null;
    updated_at: string | null;
};

export type AdminLesson = {
    id: number;
    title: string;
    slug: string;
    summary: string;
    body: string | null;
    level_id: number;
    category_ids: number[];
    is_free: boolean;
    status: LessonStatus;
    published_at: string | null;
    position: number;
    cover_url: string | null;
    has_pdf: boolean;
};

export type AdminUserRow = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    email_verified_at: string | null;
    created_at: string | null;
    is_current: boolean;
};

export type AdminRole = {
    id: number;
    name: string;
    label: string;
    users_count: number;
    permissions: string[];
    locked: boolean;
};

export type VideoSource = 'upload' | 'youtube' | 'vimeo';
export type VideoStatus = 'processing' | 'ready' | 'failed';

export type LessonVideo = {
    id: number;
    source: VideoSource;
    status: VideoStatus;
    title: string | null;
    embed_url: string | null;
    external_url: string | null;
    playlist_url: string | null;
    thumbnail_url: string | null;
    renditions: string[];
    duration_seconds: number | null;
    size_bytes: number | null;
    original_name: string | null;
    error: string | null;
};
