<template>
	<AppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-group'"
		:title="t('maps', 'My contacts')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="true"
		:open="open"
		:force-menu="false"
		@click="onContactsClick"
		@update:open="onUpdateOpen">
		<CounterBubble v-if="enabled && contacts.length"
			slot="counter">
			{{ contacts.length > 99 ? '99+' : contacts.length }}
		</CounterBubble>
		<template v-if="enabled" slot="actions">
			<ActionButton
				icon="icon-checkmark"
				@click="onToggleAllClick">
				{{ t('maps', 'Toggle all') }}
			</ActionButton>
			<ActionButton
				icon="icon-search"
				:close-after-click="true"
				@click="onZoomAllClick">
				{{ t('maps', 'Zoom') }}
			</ActionButton>
		</template>
		<template #default>
			<AppNavigationItem
				v-for="(g, gid) in groups"
				:key="gid"
				icon="icon-group"
				:title="g.name"
				:class="{ 'subitem-disabled': !g.enabled }"
				:allow-collapse="false"
				:force-menu="false"
				@click="onGroupClick(gid)">
				<CounterBubble v-if="enabled && contacts.length && g.enabled"
					slot="counter">
					{{ g.counter > 99 ? '99+' : g.counter }}
				</CounterBubble>
				<template slot="actions">
					<ActionButton v-if="enabled && contacts.length && g.enabled"
						icon="icon-search"
						:close-after-click="true"
						@click="onZoomGroupClick(gid)">
						{{ t('maps', 'Zoom') }}
					</ActionButton>
				</template>
			</AppNavigationItem>
		</template>
	</AppNavigationItem>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import CounterBubble from '@nextcloud/vue/dist/Components/CounterBubble'
import optionsController from '../optionsController'

export default {
	name: 'AppNavigationContactsItem',

	components: {
		AppNavigationItem,
		ActionButton,
		CounterBubble,
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
