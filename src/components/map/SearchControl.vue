<!--
 - SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Search field only; Map.vue handles the selected result.
-->
<template>
	<MglCustomControl position="top-left">
		<div v-show="visible" class="maps-search-control" :class="{ mobile: isMobile, desktop: !isMobile }">
			<SearchField
				class="search-field"
				:data="searchData"
				:loading="loading"
				@validate="emit('validate', $event)"
				@clear="emit('clear')" />
			<NcButton class="maps-search-control__directions"
				variant="tertiary"
				:title="t('maps', 'Find directions')"
				:aria-label="t('maps', 'Find directions')"
				@click="emit('routing-clicked')">
				<template #icon>
					<DirectionsIcon :size="20" />
				</template>
			</NcButton>
		</div>
	</MglCustomControl>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { NcButton, useIsMobile } from '@nextcloud/vue'
import { MglCustomControl } from 'vue-maplibre-gl'
import DirectionsIcon from 'vue-material-design-icons/Directions.vue'
import SearchField from './SearchField.vue'

withDefaults(defineProps<{
	visible?: boolean
	searchData: unknown[]
	loading?: boolean
}>(), {
	visible: true,
	loading: false,
})

const emit = defineEmits<{
	validate: [element: unknown]
	clear: []
	'routing-clicked': []
}>()

const isMobile = useIsMobile()
</script>

<style lang="scss" scoped>
// Leave room for the navigation toggle, which sits at the same offset in the top-left corner
.maps-search-control {
	--maps-search-offset: calc(2 * var(--default-grid-baseline));
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);
	margin-inline-start: calc(var(--default-clickable-area) + var(--maps-search-offset));

	// more specific than NcSelect's own .nc-select.v-select.select margin
	.search-field.nc-select {
		flex-grow: 1;
		margin: 0;
	}

	&__directions {
		flex-shrink: 0;
		background-color: var(--color-main-background) !important;
		box-shadow: 0 0 0 1px var(--color-border-maxcontrast);

		&:hover {
			background-color: var(--color-background-hover) !important;
		}
	}

	&.desktop {
		width: 350px;
	}

	&.mobile {
		width: calc(100vw - var(--default-clickable-area) - 3 * var(--maps-search-offset));
	}
}
</style>

<style lang="scss">
// More specific than MapLibre's own .maplibregl-ctrl-group:not(:empty), which draws a frame around the control
.maplibregl-map .maplibregl-ctrl.maplibregl-ctrl-group:has(> .maps-search-control) {
	margin: calc(2 * var(--default-grid-baseline)) 0 0 calc(2 * var(--default-grid-baseline));
	background: transparent;
	border-radius: 0;
	box-shadow: none;
}

// The search field and the routing panel take turns in the top-left corner, the hidden one must not push the other down
.maplibregl-map .maplibregl-ctrl.maplibregl-ctrl-group:has(> .maps-search-control[style*="display: none"]) {
	margin: 0;
}
</style>
