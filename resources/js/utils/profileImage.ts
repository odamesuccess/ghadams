export function profileImageUrl(value?: string | null): string | null {
    const image = value?.trim().replace(/\\/g, '/');
    if (!image) return null;
    if (/^(data:|blob:)/i.test(image)) return image;
    const storageIndex = image.toLowerCase().indexOf('/storage/');
    if (storageIndex >= 0 && /^https?:\/\//i.test(image)) {
        return image.slice(storageIndex);
    }
    if (/^https?:\/\//i.test(image) || image.startsWith('//')) return image;
    const publicIndex = image.toLowerCase().indexOf('storage/app/public/');
    let path = publicIndex >= 0 ? image.slice(publicIndex + 'storage/app/public/'.length) : image;
    path = path.replace(/^\/+/, '').replace(/^(public\/)?storage\//i, '');
    return `/storage/${path}`;
}
