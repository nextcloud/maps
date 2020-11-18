<template>
	<AppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-group'"
		:title="t('maps', 'My contacts')"
		:class="{ 'item-disabled': !selected }"
		:allow-collapse="true"
		:open="open"
		:force-menu="false"
		@click="onContactsClick"
		@update:open="onUpdateOpen">
		<template v-if="selected" slot="counter">
			{{ contacts.length }}
		</template>
		<template v-if="selected" slot="actions">
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
				<template slot="counter">
					{{ g.counter }}
				</template>
				<template slot="actions">
					<ActionButton
						icon="icon-search"
						:close-after-click="true"
						@click="onZoomGroupClick">
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
import optionsController from '../optionsController'

export default {
	name: 'AppNavigationContactsItem',

	components: {
		AppNavigationItem,
		ActionButton,
	},

	props: {
		selected: {
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
			if (!this.selected && !this.open) {
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

		},
		onZoomAllClick() {

		},
		onZoomGroupClick() {

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
