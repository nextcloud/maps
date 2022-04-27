<template>
	<AppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-category-multimedia'"
		:title="t('maps', 'My photos')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="false"
		:force-menu="enabled"
		@click="$emit('photos-clicked')">
		<CounterBubble v-show="enabled && photos.length"
			slot="counter">
			{{ photos.length > 99 ? '99+' : photos.length }}
		</CounterBubble>
		<template v-if="enabled" slot="actions">
			<ActionButton v-if="!readOnly"
				:icon="draggable ? 'icon-hand' : 'icon-hand-slash'"
				:close-after-click="false"
				@click="$emit('draggable-clicked')">
				{{ draggable ? t('maps', 'Disable photo drag') : t('maps', 'Enable photo drag') }}
			</ActionButton>
			<!--FIXME Hack empty menu looks wired-->
			<ActionButton v-else
				:icon="'icon-hand'"
				:close-after-click="false"
				@click="sayHi">
				{{ t('maps', 'Say hi') }}
			</ActionButton>
		</template>
	</AppNavigationItem>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import CounterBubble from '@nextcloud/vue/dist/Components/CounterBubble'

import optionsController from '../optionsController'
import { showInfo } from '@nextcloud/dialogs'

export default {
	name: 'AppNavigationPhotosItem',

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
		photos: {
			type: Array,
			required: true,
		},
		draggable: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		readOnly() {
			return this.photos.every((p) => !p.isUpdateable)
				|| (this.photos.length === 0 && !(optionsController.optionValues?.isCreatable && !optionsController.optionValues?.isUpdateable))
		},
	},

	methods: {
		sayHi() {
			showInfo(t('maps', 'Hi'))
		},
	},
}
</script>

<style lang="scss" scoped>
.item-disabled {
	opacity: 0.5;
}

::v-deep .icon-hand {
	opacity: 1;
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('../../img/hand.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/hand.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
	min-width: 38px !important;
	min-height: 36px !important;
}

::v-deep .icon-hand-slash {
	opacity: 1;
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask: url('../../img/hand-slash.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/hand-slash.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
	min-width: 38px !important;
	min-height: 36px !important;
}
</style>
