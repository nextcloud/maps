<!--
 - SPDX-FileCopyrightText: 2019 Paul Schwörer <hello@paulschwoerer.de>
 - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!--
	Ported from Leaflet to vue-maplibre-gl. Purely the search field UI;
	Map.vue owns the resulting state (POI markers, the coordinate-click
	marker) and decides what a selected result means, same split as
	before. Occupies the same top-left slot as RoutingControl - only
	one of the two is shown at a time.
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
		</div>
	</MglCustomControl>
</template>

<script setup lang="ts">
import { useIsMobile } from '@nextcloud/vue'
import { MglCustomControl } from 'vue-maplibre-gl'
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
</style>
