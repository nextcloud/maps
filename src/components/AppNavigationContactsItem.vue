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
		<template #counter>
			<NcCounterBubble v-show="enabled && contacts.length" :count="contacts.length" />
		</template>
		<template v-if="enabled" #actions>
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
		<template #default>
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
				<template #counter>
					<NcCounterBubble v-show="enabled && g.enabled" :count="g.counter" />
				</template>
				<template #actions>
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

<script setup>
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'
import optionsController from '../optionsController.js'
import { isPublic } from '../utils/common.js'

const props = defineProps({
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
})

const emit = defineEmits([
	'contacts-clicked',
	'toggle-all-groups',
	'zoom-all-groups',
	'zoom-group',
	'group-clicked',
	'add-to-map-all-contacts',
	'add-to-map-contact-group',
])

const open = ref(optionsController.optionValues?.contactGroupListShow === 'true')

function onContactsClick() {
	if (!props.enabled && !open.value) {
		open.value = true
		optionsController.saveOptionValues({ contactGroupListShow: 'true' })
	}
	emit('contacts-clicked')
}

function onUpdateOpen(isOpen) {
	open.value = isOpen
	optionsController.saveOptionValues({ contactGroupListShow: isOpen ? 'true' : 'false' })
}

function onToggleAllClick() {
	emit('toggle-all-groups')
}

function onZoomAllClick() {
	emit('zoom-all-groups')
}

function onZoomGroupClick(gid) {
	emit('zoom-group', gid)
}

function onGroupClick(groupName) {
	emit('group-clicked', groupName)
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
