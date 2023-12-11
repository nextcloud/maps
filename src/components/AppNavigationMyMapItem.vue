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
		<template slot="icon">
			<div class="icon icon-location" />
			<input v-show="false"
				ref="col"
				type="color"
				class="color-input"
				:value="myMap.color || '#0082c9'"
				@change="updateMyMapColor"
				@click.stop="">
		</template>
		<template slot="counter">
			&nbsp;
		</template>
		<template slot="actions">
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

<script>
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import Folder from 'vue-material-design-icons/Folder.vue'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'AppNavigationMyMapItem',

	components: {
		NcAppNavigationItem,
		NcActionButton,
		NcActionLink,
		Folder,
	},

	props: {
		myMap: {
			type: Object,
			required: true,
		},
		parentEnabled: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		isShareable() {
			return this.parentEnabled && (this.myMap.isShareable ?? true)
		},
	    isDeletable() {
	        return this.parentEnabled && (this.myMap.isDeletable ?? true)
		},
		folderUrl() {
			return generateUrl('apps/files?fileid=') + this.myMap.id
		},
	},

	methods: {
		onChangeColorClick() {
		},
		updateMyMapColor(e) {
			this.$emit('color', { myMap: this.myMap, color: e.target.value })
		},
		onRename(newName) {
			this.$emit('rename', { id: this.myMap.id, newName })
		},
	},
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
