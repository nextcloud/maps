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
				@validate="emit('validate', $event)" />
			<NcButton v-if="resultPoiNumber > 0"
				variant="tertiary"
				:aria-label="t('maps', 'Clear POIs')"
				@click="emit('clear-pois')">
				<template #icon>
					<CloseIcon :size="20" />
				</template>
			</NcButton>
		</div>
	</MglCustomControl>
</template>

<script setup lang="ts">
import { translate as t } from '@nextcloud/l10n'
import { NcButton, useIsMobile } from '@nextcloud/vue'
import { MglCustomControl } from 'vue-maplibre-gl'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import SearchField from './SearchField.vue'

withDefaults(defineProps<{
	visible?: boolean
	searchData: unknown[]
	loading?: boolean
	resultPoiNumber?: number
}>(), {
	visible: true,
	loading: false,
	resultPoiNumber: 0,
})

const emit = defineEmits<{
	validate: [element: unknown]
	'clear-pois': []
}>()

const isMobile = useIsMobile()
</script>

<style lang="scss" scoped>
.desktop {
	width: 350px;
	margin-left: 56px !important;
}

.mobile {
	width: 100vw;
}

.maps-search-control {
	display: flex;
	align-items: center;
	gap: 4px;

	.search-field {
		flex-grow: 1;
	}
}
</style>

<style lang="scss">
.maplibregl-ctrl-group:has(> .maps-search-control) {
	background: transparent;
	border-radius: 0;
	box-shadow: none;
}
</style>
