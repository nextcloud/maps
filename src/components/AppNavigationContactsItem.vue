<template>
	<AppNavigationItem
		icon="icon-group"
		:title="'toto'"
		:class="{selected: selected}"
		:allow-collapse="true"
		:open="open"
		:force-menu="false"
		@click="onContactsClick">
		<template slot="counter">
			332
		</template>
		<template slot="actions">
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
				v-for="g in groups"
				:key="g"
				icon="icon-group"
				:title="g"
				:class="{}"
				:allow-collapse="false"
				:force-menu="false"
				@click="onGroupClick(g)">
				<template slot="counter">
					12
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
	},

	data() {
		return {
			open: true,
			groups: ['aa', 'bb'],
		}
	},

	computed: {

	},

	methods: {
		onContactsClick() {
			this.$emit('contacts-clicked')
		},
		onToggleAllClick() {

		},
		onZoomAllClick() {

		},
		onZoomGroupClick() {

		},
		onGroupClick() {

		},
	},
}
</script>
