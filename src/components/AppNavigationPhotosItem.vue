<template>
	<NcAppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-category-multimedia'"
		:name="t('maps', 'My photos')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="false"
		:force-menu="enabled"
		@click="$emit('photos-clicked')">
		<template #counter>
			<span v-if="enabled && loading" class="counter-bubble__counter">
				{{ (loadedPhotos > 1000 ? Math.floor(loadedPhotos/1000).toString() + 'k' : loadedPhotos > 99 ? '99+' : loadedPhotos) + '/' + (totalPhotos > 1000 ? Math.floor(totalPhotos/1000).toString() + 'k' : totalPhotos > 99 ? '99+' : totalPhotos) }}
			</span>
			<NcCounterBubble v-else-if="enabled" :count="totalPhotos" />
		</template>
		<template v-if="enabled" #actions>
			<NcActionButton v-if="!readOnly"
				:icon="draggable ? 'icon-hand' : 'icon-hand-slash'"
				:close-after-click="false"
				@click="$emit('draggable-clicked')">
				{{ draggable ? t('maps', 'Disable photo drag') : t('maps', 'Enable photo drag') }}
			</NcActionButton>
			<!--FIXME Hack empty menu looks wired-->
			<NcActionButton v-else
				:icon="'icon-hand'"
				:close-after-click="false"
				@click="sayHi">
				{{ t('maps', 'Say hi') }}
			</NcActionButton>
			<NcActionButton
				:icon="showSuggestions ? 'icon-picture' : 'icon-picture'"
				:close-after-click="true"
				@click="$emit('suggestions-clicked')">
				{{ showSuggestions ? t('maps', 'Hide suggestions') : t('maps', 'Suggest photo locations') }}
			</NcActionButton>
			<NcActionButton
				:icon="'icon-reload'"
				:close-after-click="true"
				@click="$emit('clear-cache')">
				{{ t('maps', 'Clear photo cache') }}
			</NcActionButton>
		</template>
	</NcAppNavigationItem>
</template>

<script setup>
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import { t } from '@nextcloud/l10n'
import { showInfo } from '@nextcloud/dialogs'

defineProps({
	enabled: {
		type: Boolean,
		required: true,
	},
	loading: {
		type: Boolean,
		default: false,
	},
	loadedPhotos: {
		type: Number,
		required: true,
	},
	totalPhotos: {
		type: Number,
		required: true,
	},
	readOnly: {
		type: Boolean,
		default: false,
	},
	draggable: {
		type: Boolean,
		required: true,
	},
	showSuggestions: {
		type: Boolean,
		required: false,
		default: false,
	},
})

defineEmits([
	'photos-clicked',
	'draggable-clicked',
	'suggestions-clicked',
	'clear-cache',
])

function sayHi() {
	showInfo(t('maps', 'Hi'))
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

::v-deep .icon-picture {
	opacity: 1;
	//background-color: var(--color-main-text);
	padding: 0 !important;
	//mask: url('../../img/hand.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	//-webkit-mask: url('../../img/hand.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
	min-width: 38px !important;
	min-height: 36px !important;
}

::v-deep .icon-reload {
	opacity: 0.6;
	background-color: var(--color-main-text);
	mask: url('../../img/reload.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/reload.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}
</style>
