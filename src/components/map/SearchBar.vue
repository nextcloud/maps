<template>
	<div id="search">
		<div id="search-form">
			<input
				id="search-term"
				type="text"
				:placeholder="t('maps', 'Search…')">
			<input
				id="search-submit"
				type="submit"
				class="icon-search"
				value="">
			<button id="route-submit"
				class=""
				@click="$emit('routing-clicked')">
				<span class="icon-routing" />
			</button>
		</div>
	</div>
</template>

<script>
import { getLocale } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'

import * as network from '../../network'
import optionsController from '../../optionsController'

export default {
	name: 'SearchBar',

	props: {
		map: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			locale: getLocale(),
		}
	},

	watch: {
	},

	created() {
	},

	methods: {
	},
}
</script>

<style lang="scss" scoped>
#search {
	position: absolute;
	margin: 10px 0 0 10px;
	left: 0;
	top: 0;
	z-index: 10000;

	#search-term {
		margin: 0;
		width: 350px;
		max-width: calc(100vw - 64px);
		padding-right: 80px;
		text-overflow: ellipsis;
	}

	#search-submit {
		position: absolute;
		padding: 10px 19px;
		top: -2px;
		right: 38px;
		margin: 0;
		border: none;
		background-color: transparent;
	}

	#route-submit {
		position: absolute;
		padding: 0;
		right: 0;
		margin: 0 3px 0 0;
		top: -2px;
		border: none;
		background-color: transparent;
	}

	.icon-routing {
		opacity: 1;
		background-color: black;
		padding: 0 !important;
		mask: url('../../../img/routing.svg') no-repeat;
		mask-size: 16px auto;
		mask-position: center;
		-webkit-mask: url('../../../img/routing.svg') no-repeat;
		-webkit-mask-size: 16px auto;
		-webkit-mask-position: center;
		min-width: 38px !important;
		min-height: 36px !important;
	}
}
</style>
