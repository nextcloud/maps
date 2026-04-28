<template>
	<NcAppNavigationItem
		:name="myMap.name"
		:class="{ 'subitem-disabled': !myMap.enabled }"
		:allow-collapse="false"
		:force-menu="false"
		:editable="!!myMap.id"
		:edit-label="t('maps', 'Rename')"
		@click="$emit('click', myMap)"
		@update:name="onRename">
		<template #icon>
			<div class="icon icon-location" />
			<input v-show="false"
				ref="col"
				type="color"
				class="color-input"
				:value="myMap.color || '#0082c9'"
				@change="updateMyMapColor"
				@click.stop="">
		</template>
		<template #counter>
			&nbsp;
		</template>
		<template #actions>
			<NcActionLink v-if="myMap.id"
				target="_blank"
				:href="folderUrl"
				:close-after-click="true">
				<template #icon>
					<Folder :size="20" />
				</template>
				{{ t('maps', 'Open folder') }}
			</NcActionLink>
			<NcActionButton v-if="false"
				:close-after-click="false"
				@click="onChangeColorClick">
				<template #icon>
					<div class="icon-colorpicker" />
				</template>
				{{ t('maps', 'Change color') }}
			</NcActionButton>
			<NcActionButton v-if="myMap.id"
				icon="icon-share"
				:close-after-click="false"
				@click="$emit('share', myMap)">
				{{ t('maps', 'Share') }}
			</NcActionButton>
			<NcActionButton v-if="isDeletable"
				icon="icon-delete"
				:close-after-click="true"
				@click="$emit('delete', myMap.id)">
				{{ t('maps', 'Delete') }}
			</NcActionButton>
		</template>
	</NcAppNavigationItem>
</template>

<script setup>
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import Folder from 'vue-material-design-icons/Folder.vue'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { ref, computed } from 'vue'

const props = defineProps({
	myMap: {
		type: Object,
		required: true,
	},
	parentEnabled: {
		type: Boolean,
		default: true,
	},
})

const emit = defineEmits(['click', 'rename', 'delete', 'share', 'color'])

const col = ref(null)

const isShareable = computed(() => {
	return props.parentEnabled && (props.myMap.isShareable ?? true)
})

const isDeletable = computed(() => {
	return props.parentEnabled && (props.myMap.isDeletable ?? true)
})

const folderUrl = computed(() => {
	return generateUrl('apps/files?fileid=') + props.myMap.id
})

function onChangeColorClick() {
}

function updateMyMapColor(e) {
	emit('color', { myMap: props.myMap, color: e.target.value })
}

function onRename(newName) {
	emit('rename', { id: props.myMap.id, newName })
}
</script>

<style lang="scss" scoped>
.subitem-disabled {
	opacity: 0.5;
}

.icon-colorpicker {
	opacity: 1;
	mask: url('../../img/color_picker.svg') no-repeat;
	-webkit-mask: url('../../img/color_picker.svg') no-repeat;
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
	width: 44px;
	height: 44px;
}
</style>
