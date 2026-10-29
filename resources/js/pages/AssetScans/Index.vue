<script setup lang="ts">
defineProps<{
  asset: {
    asset_code: string
    assetName?: { assetname: string } | null
    unit?: { name: string } | null
    assetLocation?: { locationname: string } | null
  }
  scannedAt: string
}>()
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-background px-4">
    <div class="w-full max-w-sm rounded-xl border bg-card p-6 shadow-lg">
      <h2 class="text-center text-lg font-semibold mb-4">
        Asset Information
      </h2>

      <div class="space-y-2 text-sm">
        <p>
          <strong>Asset Code:</strong>
          <span>{{ asset.asset_code }}</span>
        </p>

        <p>
          <strong>Name:</strong>
          <span>{{ asset.assetName?.assetname ?? 'Not Assigned' }}</span>
        </p>

        <p>
          <strong>Unit:</strong>
          <span>{{ asset.unit?.name ?? '—' }}</span>
        </p>

        <p>
          <strong>Location:</strong>
          <span>{{ asset.assetLocation?.locationname ?? '—' }}</span>
        </p>
      </div>

      <hr class="my-4 border-border" />

      <p class="text-xs text-muted-foreground text-center">
        Scanned at {{ scannedAt }}
      </p>
    </div>
  </div>
</template>
