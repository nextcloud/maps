<template>
	<div v-if="favorite"
		id="favorite-tab">
		<FavoriteEditionForm
			:favorite="favorite"
			:categories="categories"
			@edit="emit('edit', $event)"
			@delete="emit('delete', $event)" />
	</div>
</template>

<script setup>
import FavoriteEditionForm from './FavoriteEditionForm.vue'

defineProps({
	favorite: {
		required: true,
		validator: prop => typeof prop === 'object' || prop === null,
	},
	categories: {
		type: Object,
		required: true,
	},
})

const emit = defineEmits(['edit', 'delete'])
</script>

<style lang="scss" scoped>
#favorite-tab {
	padding: 0 10px 0 10px;
}
</style>
