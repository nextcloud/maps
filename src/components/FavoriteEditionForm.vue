<template>
	<form v-if="favorite.isUpdateable"
		class="favorite-edition"
		@submit.prevent="onSubmit">
		<NcTextField v-model="name"
			:label="t('maps', 'Name')" />

		<NcSelect :model-value="selectedCategory"
			class="favorite-edition__category"
			:input-label="t('maps', 'Category')"
			:options="categoryOptions"
			:clearable="false"
			label="label"
			@update:model-value="onCategorySelected"
			@search="onCategorySearch" />

		<NcTextArea v-model="comment"
			:label="t('maps', 'Comment')"
			resize="vertical" />

		<div class="favorite-edition__location">
			<NcTextField v-model="lat"
				type="number"
				step="any"
				:label="t('maps', 'Latitude')"
				:error="!latValid"
				:helper-text="latValid ? '' : t('maps', 'Between -90 and 90')" />
			<NcTextField v-model="lng"
				type="number"
				step="any"
				:label="t('maps', 'Longitude')"
				:error="!lngValid"
				:helper-text="lngValid ? '' : t('maps', 'Between -180 and 180')" />
		</div>

		<div class="favorite-edition__actions">
			<NcButton v-if="favorite.isDeletable"
				variant="error"
				@click="emit('delete', favorite.id)">
				<template #icon>
					<IconDelete :size="20" />
				</template>
				{{ t('maps', 'Delete') }}
			</NcButton>
			<NcButton type="submit"
				variant="primary"
				:disabled="!dirty || !latValid || !lngValid">
				<template #icon>
					<IconCheck :size="20" />
				</template>
				{{ t('maps', 'Save') }}
			</NcButton>
		</div>
	</form>

	<div v-else class="favorite-edition">
		<dl class="favorite-edition__readonly">
			<div v-for="line in readonlyLines" :key="line.title">
				<dt>{{ line.title }}</dt>
				<dd>{{ line.value }}</dd>
			</div>
		</dl>
		<div v-if="favorite.isDeletable" class="favorite-edition__actions">
			<NcButton variant="error"
				@click="emit('delete', favorite.id)">
				<template #icon>
					<IconDelete :size="20" />
				</template>
				{{ t('maps', 'Delete') }}
			</NcButton>
		</div>
	</div>
</template>

<script setup lang="ts">
import type { Favorite } from './map/FavoritesLayer.vue'

import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcSelect, NcTextArea, NcTextField } from '@nextcloud/vue'
import { computed, ref, watch } from 'vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconDelete from 'vue-material-design-icons/TrashCanOutline.vue'

interface CategoryOption {
	label: string
	category: string
}

const props = defineProps<{
	favorite: Favorite
	categories: Record<string, { name: string }>
}>()

const emit = defineEmits<{
	edit: [favorite: Favorite]
	delete: [id: number]
}>()

const name = ref('')
const category = ref('')
const comment = ref('')
const lat = ref<string | number>('')
const lng = ref<string | number>('')
const newCategoryOption = ref<CategoryOption | null>(null)

/**
 * Reset the form to the values of the favorite.
 */
function reset(): void {
	name.value = props.favorite.name ?? ''
	category.value = props.favorite.category ?? ''
	comment.value = props.favorite.comment ?? ''
	lat.value = props.favorite.lat
	lng.value = props.favorite.lng
	newCategoryOption.value = null
}

watch(() => props.favorite, reset, { immediate: true, deep: true })

const selectedCategory = computed<CategoryOption>(() => ({ label: category.value, category: category.value }))

const categoryOptions = computed<CategoryOption[]>(() => {
	const options = Object.values(props.categories).map((c) => ({ label: c.name, category: c.name }))
	return newCategoryOption.value ? [newCategoryOption.value, ...options] : options
})

/**
 * Offer the search text as a new category when no existing category matches it.
 *
 * @param query the text typed into the category select
 */
function onCategorySearch(query: string): void {
	const trimmed = query.trim()
	newCategoryOption.value = trimmed === '' || trimmed in props.categories
		? null
		: { label: t('maps', 'New category {n}', { n: trimmed }), category: trimmed }
}

/**
 * @param option the chosen category
 */
function onCategorySelected(option: CategoryOption | null): void {
	if (option) {
		category.value = option.category
	}
}

/**
 * @param value the coordinate as typed
 * @param limit the maximum absolute value
 */
function isValidCoordinate(value: string | number, limit: number): boolean {
	if (value === '') {
		return false
	}
	const number = Number(value)
	return Number.isFinite(number) && Math.abs(number) <= limit
}

const latValid = computed(() => isValidCoordinate(lat.value, 90))
const lngValid = computed(() => isValidCoordinate(lng.value, 180))

const dirty = computed(() => name.value !== (props.favorite.name ?? '')
	|| category.value !== (props.favorite.category ?? '')
	|| comment.value !== (props.favorite.comment ?? '')
	|| Number(lat.value) !== Number(props.favorite.lat)
	|| Number(lng.value) !== Number(props.favorite.lng))

const readonlyLines = computed(() => [
	{ title: t('maps', 'Name'), value: props.favorite.name || t('maps', 'No name') },
	{ title: t('maps', 'Category'), value: props.favorite.category },
	{ title: t('maps', 'Comment'), value: props.favorite.comment },
	{ title: t('maps', 'Location'), value: `${props.favorite.lat}, ${props.favorite.lng}` },
].filter((line) => line.value))

function onSubmit(): void {
	if (!dirty.value || !latValid.value || !lngValid.value) {
		return
	}
	emit('edit', {
		...props.favorite,
		name: name.value,
		category: category.value,
		comment: comment.value,
		lat: Number(lat.value),
		lng: Number(lng.value),
	})
}
</script>

<style lang="scss" scoped>
.favorite-edition {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-block: 8px;

	&__category {
		width: 100%;
	}

	&__location {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 8px;
	}

	&__actions {
		display: flex;
		justify-content: flex-end;
		gap: 8px;
	}

	&__readonly {
		margin: 0;

		div {
			padding-block: 6px;
		}

		// server.css lays out every dt/dd as fixed-width inline blocks
		dt,
		dd {
			display: block;
			width: auto;
			margin: 0;
			padding: 0;
			white-space: pre-wrap;
			text-align: start;
		}

		dt {
			color: var(--color-text-maxcontrast);
		}
	}
}
</style>
