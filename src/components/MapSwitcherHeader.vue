<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="map-switcher">
		<NcPopover popup-role="dialog"
			:shown="open"
			popover-base-class="map-switcher-popover"
			@update:shown="open = $event">
			<template #trigger>
				<button ref="triggerEl"
					type="button"
					class="map-switcher__trigger"
					:title="t('maps', 'Switch map')"
					:aria-label="t('maps', 'Switch map')"
					@click="open = true">
					<NcAvatar :display-name="currentMap.name"
						:is-no-user="true"
						:size="44"
						:disable-menu="true"
						:hide-status="true" />
					<span class="map-switcher__info">
						<span class="map-switcher__name">{{ currentMap.name }}</span>
						<span class="map-switcher__subname">{{ subname(currentMap) }}</span>
					</span>
					<NcLoadingIcon v-if="loading" :size="20" />
					<IconChevronDown v-else :size="20" />
				</button>
			</template>

			<div class="map-switcher__panel">
				<div class="map-switcher__current">
					<NcAvatar :display-name="currentMap.name"
						:is-no-user="true"
						:size="44"
						:disable-menu="true"
						:hide-status="true" />
					<span class="map-switcher__info">
						<span class="map-switcher__name">{{ currentMap.name }}</span>
						<span class="map-switcher__subname">{{ subname(currentMap) }}</span>
					</span>
					<NcActions v-if="currentMap.id"
						:aria-label="t('maps', 'Map actions')"
						@open="newName = currentMap.name">
						<NcActionInput v-model="newName"
							:label="t('maps', 'Rename')"
							@submit="submitRename">
							<template #icon>
								<IconPencil :size="20" />
							</template>
						</NcActionInput>
						<NcActionLink :href="folderUrl(currentMap)" target="_blank">
							<template #icon>
								<IconFolder :size="20" />
							</template>
							{{ t('maps', 'Open folder') }}
						</NcActionLink>
						<NcActionButton v-if="currentMap.isShareable ?? true"
							:close-after-click="true"
							@click="onShare">
							<template #icon>
								<IconShare :size="20" />
							</template>
							{{ t('maps', 'Share') }}
						</NcActionButton>
						<NcActionButton v-if="currentMap.isDeletable ?? true"
							:close-after-click="true"
							@click="onDelete">
							<template #icon>
								<IconDelete :size="20" />
							</template>
							{{ t('maps', 'Delete') }}
						</NcActionButton>
					</NcActions>
				</div>

				<template v-if="otherMaps.length > 0">
					<hr class="map-switcher__divider">
					<span class="map-switcher__caption">{{ t('maps', 'Switch to map') }}</span>
					<ul class="map-switcher__list">
						<NcListItem v-for="myMap in otherMaps"
							:key="myMap.id ?? 'default'"
							:name="myMap.name"
							:one-line="true"
							@click="onSwitch(myMap)">
							<template #icon>
								<NcAvatar :display-name="myMap.name"
									:is-no-user="true"
									:size="32"
									:disable-menu="true"
									:hide-status="true" />
							</template>
						</NcListItem>
					</ul>
				</template>

				<hr class="map-switcher__divider">
				<NcButton variant="secondary"
					:wide="true"
					@click="onAdd">
					<template #icon>
						<IconPlus :size="20" />
					</template>
					{{ t('maps', 'New map') }}
				</NcButton>
			</div>
		</NcPopover>
	</div>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import {
	NcActionButton,
	NcActionInput,
	NcActionLink,
	NcActions,
	NcAvatar,
	NcButton,
	NcListItem,
	NcLoadingIcon,
	NcPopover,
} from '@nextcloud/vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import IconChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import IconFolder from 'vue-material-design-icons/Folder.vue'
import IconPencil from 'vue-material-design-icons/Pencil.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconShare from 'vue-material-design-icons/ShareVariant.vue'
import IconDelete from 'vue-material-design-icons/TrashCanOutline.vue'

/** A map of the user; the default map has no id and no folder. */
export interface MyMap {
	id: number | null
	name: string
	path?: string
	enabled: boolean
	isShareable?: boolean
	isDeletable?: boolean
}

const props = defineProps<{
	myMaps: MyMap[]
	loading?: boolean
}>()

const emit = defineEmits<{
	switch: [myMap: MyMap]
	add: [name: string]
	rename: [payload: { id: number, newName: string }]
	share: [myMap: MyMap]
	delete: [id: number]
}>()

const open = ref(false)
const triggerEl = ref<HTMLElement | null>(null)

/** Inset of the panel from the edges of the navigation it covers */
const PANEL_INSET = 8

/**
 * Pin the panel over the navigation, so it wraps the header it was opened from.
 */
function updatePanelGeometry(): void {
	const navigation = triggerEl.value?.closest('#app-navigation-vue')
	if (!navigation) {
		return
	}
	const rect = navigation.getBoundingClientRect()
	const style = document.documentElement.style
	style.setProperty('--map-switcher-top', `${rect.top + PANEL_INSET}px`)
	style.setProperty('--map-switcher-left', `${rect.left + PANEL_INSET}px`)
	style.setProperty('--map-switcher-width', `${rect.width - 2 * PANEL_INSET}px`)
	style.setProperty('--map-switcher-max-height', `${window.innerHeight - rect.top - 2 * PANEL_INSET}px`)
}

/**
 * Keep the panel pinned while it is open and the window is resized.
 */
function onResize(): void {
	if (open.value) {
		updatePanelGeometry()
	}
}

watch(open, (isOpen) => {
	if (isOpen) {
		updatePanelGeometry()
		window.addEventListener('resize', onResize)
	} else {
		window.removeEventListener('resize', onResize)
	}
})

onBeforeUnmount(() => window.removeEventListener('resize', onResize))
const newName = ref('')

const defaultMap: MyMap = { id: null, name: t('maps', 'Default'), enabled: true }
const currentMap = computed(() => props.myMaps.find((m) => m.enabled) ?? props.myMaps[0] ?? defaultMap)
const otherMaps = computed(() => props.myMaps.filter((m) => m !== currentMap.value))

/**
 * @param myMap the map to describe
 */
function subname(myMap: MyMap): string {
	return myMap.id === null ? t('maps', 'Default map') : (myMap.path ?? '')
}

/**
 * @param myMap the map whose folder to link to
 */
function folderUrl(myMap: MyMap): string {
	return generateUrl('/apps/files/?fileid={id}', { id: myMap.id })
}

function submitRename(): void {
	const name = newName.value.trim()
	if (name && currentMap.value.id !== null && name !== currentMap.value.name) {
		emit('rename', { id: currentMap.value.id, newName: name })
	}
}

/**
 * @param myMap the map to switch to
 */
function onSwitch(myMap: MyMap): void {
	open.value = false
	emit('switch', myMap)
}

function onAdd(): void {
	open.value = false
	emit('add', t('maps', 'New map'))
}

function onShare(): void {
	open.value = false
	emit('share', currentMap.value)
}

function onDelete(): void {
	if (currentMap.value.id === null) {
		return
	}
	open.value = false
	emit('delete', currentMap.value.id)
}
</script>

<style lang="scss" scoped>
.map-switcher {
	padding: calc(2 * var(--default-grid-baseline));

	// The popover wraps its trigger, stretch it so the trigger spans the full width
	:deep(.v-popper) {
		width: 100%;
	}

	&__trigger {
		display: flex;
		align-items: center;
		gap: calc(3 * var(--default-grid-baseline));
		width: 100%;
		min-width: 0;
		margin: 0;
		padding: var(--default-grid-baseline);
		border: none;
		border-radius: var(--border-radius-element);
		background: none;
		text-align: start;
		cursor: pointer;

		&:hover,
		&:focus,
		&:active {
			background-color: transparent !important;
		}
	}

	&__info {
		display: flex;
		flex: 1 1 auto;
		flex-direction: column;
		min-width: 0;
	}

	&__name,
	&__subname {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__name {
		font-weight: bold;
	}

	&__subname {
		// the server's global button styles make all button text bold
		font-weight: normal;
		color: var(--color-text-maxcontrast);
		font-size: var(--font-size-small, 13px);
	}

	&__panel {
		display: flex;
		flex-direction: column;
		gap: var(--default-grid-baseline);
		width: 100%;
		max-height: var(--map-switcher-max-height, 80vh);
		padding: calc(2 * var(--default-grid-baseline));
	}

	&__current {
		display: flex;
		align-items: center;
		gap: calc(3 * var(--default-grid-baseline));
		min-width: 0;
	}

	&__divider {
		width: 100%;
		margin: var(--default-grid-baseline) 0;
		border: none;
		border-top: 1px solid var(--color-border);
	}

	&__caption {
		margin-inline-start: var(--default-grid-baseline);
		font-weight: bold;
	}

	&__list {
		max-height: 300px;
		overflow-y: auto;

		:deep(.list-item__wrapper) {
			padding-inline: 0;
		}
	}
}
</style>

<style lang="scss">
// Pinned over the navigation by updatePanelGeometry(), instead of the popover's own placement
.map-switcher-popover {
	position: fixed !important;
	top: var(--map-switcher-top, 0) !important;
	left: var(--map-switcher-left, 0) !important;
	width: var(--map-switcher-width, 300px);
	transform: none !important;

	.v-popper__arrow-container {
		display: none;
	}
}
</style>
