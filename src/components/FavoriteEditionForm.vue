<template>
	<div class="favorite-edition">
		<div class="favorite-form">
			<NcTextField
				v-model="name"
				:label="t('maps', 'Name')"
				:placeholder="t('maps', 'Favorite name')"
				:readonly="!favorite.isUpdateable">
				<template #icon>
					<StarIcon :size="20" />
				</template>
			</NcTextField>

			<NcSelect v-if="favorite.isUpdateable"
				v-model="selectedCategory"
				label="label"
				:placeholder="t('maps', 'Category')"
				:options="formattedCategories"
				:limit="8"
				@search="onSearchChange">
				<template #singleLabel="{ option }">
					{{ option ? option.catid : '' }}
				</template>
			</NcSelect>
			<NcTextField v-else
				:model-value="selectedCategory.catid"
				:label="t('maps', 'Category')"
				readonly>
				<template #icon>
					<LabelOutlineIcon :size="20" />
				</template>
			</NcTextField>

			<NcTextArea
				v-model="comment"
				:label="t('maps', 'Comment')"
				:placeholder="t('maps', 'Comment')"
				resize="vertical"
				:readonly="!favorite.isUpdateable" />

			<NcTextField
				v-model="location"
				:label="t('maps', 'Location')"
				:placeholder="t('maps', 'Location')"
				:readonly="!favorite.isUpdateable">
				<template #icon>
					<MapMarkerOutlineIcon :size="20" />
				</template>
			</NcTextField>
		</div>
		<div class="buttons">
			<NcButton
				:disabled="!favorite.isUpdateable"
				variant="primary"
				@click="onOkClick">
				<template #icon>
					<ContentSaveIcon :size="20" />
				</template>
				{{ t('maps', 'Save') }}
			</NcButton>
			<NcButton :disabled="!favorite.isUpdateable"
				@click="onDeleteClick">
				<template #icon>
					<TrashCanIcon :size="20" />
				</template>
				{{ t('maps', 'Delete') }}
			</NcButton>
		</div>
	</div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import LabelOutlineIcon from 'vue-material-design-icons/LabelOutline.vue'
import MapMarkerOutlineIcon from 'vue-material-design-icons/MapMarkerOutline.vue'
import StarIcon from 'vue-material-design-icons/Star.vue'
import ContentSaveIcon from 'vue-material-design-icons/ContentSave.vue'
import TrashCanIcon from 'vue-material-design-icons/TrashCan.vue'

const props = defineProps({
	favorite: {
		type: Object,
		required: true,
	},
	categories: {
		type: Object,
		required: true,
	},
})

const emit = defineEmits(['edit', 'delete'])

const name = ref(props.favorite.name ?? '')
const category = ref(props.favorite.category)
const comment = ref(props.favorite.comment)
const lat = ref(props.favorite.lat)
const lng = ref(props.favorite.lng)
const newCategoryOption = ref(null)
const selectedCategory = ref({
	label: props.favorite.category,
	catid: props.favorite.category,
})

const location = computed({
	get: () => `${lat.value},${lng.value}`,
	set: (value) => {
		const [newLat, newLng] = value.split(',')
		lat.value = newLat
		lng.value = newLng
	},
})

const formattedCategories = computed(() => {
	const options = Object.values(props.categories).map((c) => ({
		label: c.name,
		catid: c.name,
	}))
	return newCategoryOption.value ? [newCategoryOption.value, ...options] : options
})

watch(() => props.favorite, () => {
	name.value = props.favorite.name
	category.value = props.favorite.category
	selectedCategory.value = { label: props.favorite.category, catid: props.favorite.category }
	comment.value = props.favorite.comment
	lat.value = props.favorite.lat
	lng.value = props.favorite.lng
}, { deep: true })

watch(selectedCategory, (option) => {
	category.value = option ? option.catid : ''
})

function onSearchChange(query) {
	if (query === '' || Object.keys(props.categories).includes(query)) {
		newCategoryOption.value = null
	} else {
		newCategoryOption.value = {
			label: t('maps', 'New category {n}', { n: query }),
			catid: query,
		}
	}
}

function onOkClick() {
	emit('edit', {
		...props.favorite,
		name: name.value,
		category: category.value,
		comment: comment.value,
		lat: lat.value,
		lng: lng.value,
	})
}

function onDeleteClick() {
	emit('delete', props.favorite.id)
}
</script>

<style lang="scss" scoped>
.favorite-form {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.buttons {
	display: flex;
	gap: 8px;
	margin-top: 16px;

	button {
		flex: 1;
	}
}
</style>
