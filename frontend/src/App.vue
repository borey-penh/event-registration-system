<script setup>
import { onMounted } from 'vue'

// Signal "the app booted" to the ES5 boot guard in index.html: it shows a
// "open in Chrome" panel when the bundle can't run (old webviews); any
// successful mount must hide it again, including route error states.
onMounted(() => {
  const fallback = document.getElementById('boot-fallback')
  if (fallback) fallback.remove()
})
</script>

<template>
  <RouterView />
</template>
