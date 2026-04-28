<template>
	<div ref="el" style="display:none">
		<div class="maps-search-control maplibregl-ctrl maplibregl-ctrl-group" :class="{'mobile':isMobile, 'desktop': !isMobile}">
			<div id="search">
				<SearchField
					class="search-field"
					:data="searchData"
					:loading="loading"
					@validate="$emit('validate', $event)" />
				<button
					v-tooltip="{ content: t('maps', 'Find directions') }"
					class="bar-button"
					@click="$emit('routing-clicked')">
					<span class="icon icon-routing" />
				</button>
				<button v-if="resultPoiNumber > 0"
					v-tooltip="{ content: t('maps', 'Clear POIs') }"
					class="bar-button"
					@click="$emit('clear-pois')">
					<span class="icon icon-close" />
				</button>
			</div>
		</div>
	</div>
</template>

<script>
import { getLocale } from '@nextcloud/l10n'
import { useControl } from '@indoorequal/vue-maplibre-gl'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { computed, ref, onMounted } from 'vue'

import SearchField from './SearchField.vue'

export default {
	name: 'SearchControl',

	components: {
		SearchField,
	},

	setup() {
		const isMobile = useIsMobile()
		const el = ref(null)

		onMounted(() => {
			useControl(() => ({
				onAdd() {
					this._container = el.value?.children[0]
					return this._container
				},
				onRemove() {},
			}), { position: 'top-left' })
		})

		return { isMobile: computed(() => isMobile.value), el }
	},

	props: {
		map: {
			type: Object,
			required: true,
		},
		searchData: {
			type: Array,
			required: true,
		},
		loading: {
			type: Boolean,
			default: false,
		},
		resultPoiNumber: {
			type: Number,
			default: 0,
		},
	},

	data() {
		return {
			locale: getLocale(),
		}
	},

	methods: {
	},
}
</script>

<style lang="scss" scoped>
.desktop {
	width: 350px;
	margin-left: 56px !important;
}
.mobile {
	width: 100vw;
	background: var(--color-main-background);
	padding-left: 56px;
	padding-right: 56px;
	padding-top: 10px;
	padding-bottom: 7px;
	margin-top: 0px !important;
}
.maps-search-control {
	z-index: 99999999 !important;
	#search {
		display: flex;
		> button {
			min-width: 34px;
			width: 34px;
			height: 34px;
			padding: 0;
		}
		.multiselect {
			flex-grow: 1;
			min-width: 100px;
			background: transparent;
		}
	}

	.bar-button {
		margin: 5px;
		padding: 0px 0px 2px;

		.icon-routing {
			margin-right: 2px;
		}
		.icon {
			opacity: 0.5;
		}
		&:hover .icon {
			opacity: 1;
		}
	}
}

::v-deep .icon-dot-circle {
	background-color: var(--color-main-text);
	mask: url('../../../img/dot-circle.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../../img/dot-circle.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}
</style>
