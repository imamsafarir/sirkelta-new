<script setup>
import { cn } from "cn";

const props = defineProps({
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});
</script>

<template>
  <div
    data-slot="card-title"
    :class="
      cn(
        'font-sans text-base leading-snug font-bold tracking-tight group-data-[size=sm]/card:text-sm',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
