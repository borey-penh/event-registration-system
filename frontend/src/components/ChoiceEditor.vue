<script setup>
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  multiple: { type: Boolean, default: false }, // checkbox vs radio icon
})
const emit = defineEmits(['update:modelValue'])

function update(index, value) {
  const next = [...props.modelValue]
  next[index] = value
  emit('update:modelValue', next)
}

function add() {
  emit('update:modelValue', [...props.modelValue, ''])
}

function remove(index) {
  const next = props.modelValue.filter((_, i) => i !== index)
  emit('update:modelValue', next)
}
</script>

<template>
  <div class="choice-editor">
    <div v-for="(opt, i) in modelValue" :key="i" class="choice-row">
      <span class="choice-icon" :class="{ multi: multiple }"></span>
      <input
        :value="opt"
        class="choice-input"
        :placeholder="`Option ${i + 1}`"
        @input="update(i, $event.target.value)"
      />
      <button class="choice-remove" type="button" title="Remove option" @click="remove(i)">✕</button>
    </div>

    <button class="choice-add" type="button" @click="add">
      <span class="choice-icon" :class="{ multi: multiple }"></span>
      Add option
    </button>
  </div>
</template>

<style scoped>
.choice-editor { display: flex; flex-direction: column; gap: 6px; }
.choice-row {
  display: flex; align-items: center; gap: 10px;
  background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
  padding: 6px 10px; transition: border-color 0.15s ease;
}
.choice-row:hover { border-color: #99f6e4; }
.choice-row:focus-within { border-color: #14b8a6; background: #fff; }
.choice-icon {
  width: 16px; height: 16px; border: 2px solid #94a3b8; border-radius: 50%;
  flex-shrink: 0;
}
.choice-icon.multi { border-radius: 4px; }
.choice-input {
  flex: 1; border: 0; background: transparent; padding: 6px 0;
  font-size: 14px; color: #0f172a;
}
.choice-input:focus { outline: none; }
.choice-remove {
  border: 0; background: transparent; color: #cbd5e1; cursor: pointer;
  font-size: 14px; padding: 4px 6px; border-radius: 6px; line-height: 1;
}
.choice-remove:hover { color: #dc2626; background: #fee2e2; }
.choice-add {
  display: flex; align-items: center; gap: 10px;
  border: 0; background: transparent; color: #0f766e;
  font-size: 14px; font-weight: 600; padding: 8px 10px;
  cursor: pointer; border-radius: 8px; text-align: left;
}
.choice-add:hover { background: #f0fdfa; }
.choice-add .choice-icon { border-color: #14b8a6; }
</style>
