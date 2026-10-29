<script setup lang="ts">
defineProps<{ asset: any }>()
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 to-slate-800 text-white">
    <div class="max-w-xl w-full p-6 rounded-xl bg-black/40 space-y-3">

      <div v-if="asset.asset_image">
        <img
          :src="`/storage/${asset.asset_image}`"
          class="max-h-64 rounded border"
        />
      </div>

      <div><strong>Asset Code:</strong> {{ asset.asset_code }}</div>
      <div><strong>Name:</strong> {{ asset.assetName?.assetname }}</div>
      <div><strong>Brand:</strong> {{ asset.brand }}</div>
      <div><strong>Location:</strong> {{ asset.unit?.locationname }}</div>
      <div>
        <strong>Condition:</strong>
        {{ asset.source?.name ?? '—' }}
      </div>
      <div><strong>Status:</strong> {{ asset.status }}</div>

    </div>
  </div>
</template>
