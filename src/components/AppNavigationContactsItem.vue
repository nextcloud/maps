<template>
	<NcAppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-group'"
		:name="t('maps', 'My contacts')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="true"
		:open="open"
		:force-menu="false"
		@click="onContactsClick"
		@update:open="onUpdateOpen">
		<NcCounterBubble v-show="enabled && contacts.length"
			slot="counter">
			{{ contacts.length > 99 ? '99+' : contacts.length }}
		</NcCounterBubble>
		<template v-if="enabled" slot="actions">
			<NcActionButton
				icon="icon-checkmark"
				@click="onToggleAllClick">
				{{ t('maps', 'Toggle all') }}
			</NcActionButton>
			<NcActionButton
				icon="icon-search"
				:close-after-click="true"
				@click="onZoomAllClick">
				{{ t('maps', 'Zoom') }}
			</NcActionButton>
			<NcActionButton v-if="!isPublic()"
				icon="icon-share"
				:close-after-click="true"
				@click="$emit('add-to-map-all-contacts')">
				{{ t('maps', 'Copy to map') }}
			</NcActionButton>
		</template>
		<template slot="default">
			<b v-show="false">dummy</b>
			<NcAppNavigationItem
				v-for="(g, gid) in groups"
				:key="gid"
				icon="icon-group"
				:name="g.name"
				:class="{ 'subitem-disabled': !g.enabled }"
				:allow-collapse="false"
				:force-menu="false"
				@click="onGroupClick(gid)">
				<NcCounterBubble v-show="enabled && g.enabled"
					slot="counter">
					{{ g.counter > 99 ? '99+' : g.counter }}
				</NcCounterBubble>
				<template slot="actions">
					<NcActionButton v-if="enabled && g.enabled"
						icon="icon-search"
						:disabled="!g.enabled || g.counter === 0"
						:close-after-click="true"
						@click="onZoomGroupClick(gid)">
						{{ t('maps', 'Zoom') }}
					</NcActionButton>
					<NcActionButton v-if="!isPublic()"
						icon="icon-share"
						:close-after-click="true"
						@click="$emit('add-to-map-contact-group', gid)">
						{{ t('maps', 'Copy to map') }}
					</NcActionButton>
				</template>
			</NcAppNavigationItem>
		</template>
	</NcAppNavigationItem>
</template>

<script>
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'
import optionsController from '../optionsController.js'
import { isPublic } from '../utils/common.js'

export default {
	name: 'AppNavigationContactsItem',

	components: {
		NcAppNavigationItem,
		NcActionButton,
		NcCounterBubble,
	},

	props: {
		enabled: {
			type: Boolean,
			required: true,
		},
		loading: {
			type: Boolean,
			default: false,
		},
		contacts: {
			type: Array,
			required: true,
		},
		groups: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			open: optionsController.optionValues?.contactGroupListShow === 'true',
		}
	},

	computed: {

	},

	methods: {
		onContactsClick() {
			if (!this.enabled && !this.open) {
				this.open = true
				optionsController.saveOptionValues({ contactGroupListShow: 'true' })
			}
			this.$emit('contacts-clicked')
		},
		onUpdateOpen(isOpen) {
			this.open = isOpen
			optionsController.saveOptionValues({ contactGroupListShow: isOpen ? 'true' : 'false' })
		},
		onToggleAllClick() {
			this.$emit('toggle-all-groups')
		},
		onZoomAllClick() {
			this.$emit('zoom-all-groups')
		},
		onZoomGroupClick(gid) {
			this.$emit('zoom-group', gid)
		},
		onGroupClick(groupName) {
			this.$emit('group-clicked', groupName)
		},
		isPublic() {
			return isPublic()
		},
	},
}
</script>

<style lang="scss" scoped>
.item-disabled {
	opacity: 0.5;
}

.subitem-disabled {
	opacity: 0.5;
}
</style>
