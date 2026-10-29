export default {
  index: () => '/assetlocations',
  create: () => '/assetlocations/create',
  show: (id: number) => `/assetlocations/${id}`,
  edit: (id: number) => `/assetlocations/${id}/edit`,
}
