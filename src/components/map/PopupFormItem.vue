<!--
  - @copyright Copyright (c) 2019 Paul Schwörer <hello@paulschwoerer.de>
  -
  - @author Paul Schwörer <hello@paulschwoerer.de>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div class="form-item">
		<span class="icon" :class="icon" />
		<div class="input-wrapper">
			<template v-if="allowEdits">
				<textarea
					v-if="type === 'textarea'"
					:placeholder="placeholder"
					:value="value"
					rows="4"
					@input="$emit('input', $event.target.value)" />

				<input
					v-else
					:type="type"
					:placeholder="placeholder"
					:value="value"
					@input="$emit('input', $event.target.value)">
			</template>
			<template v-else>
				<span>{{ value }}</span>
			</template>
		</div>
	</div>
</template>

<script>
import VueTypes from 'vue-types'

export default {
	name: 'PopupFormItem',

	props: {
		icon: VueTypes.string.isRequired.def(''),
		value: VueTypes.any.isRequired.def(null),
		placeholder: VueTypes.string.def(''),
		type: VueTypes.oneOf(['textarea', 'text']).def('text'),
		allowEdits: VueTypes.bool.def(true),
	},
}
</script>

<style scoped lang="scss">
$spacing: 0.5em;

::v-deep .form-item {
	width: 100%;
	margin: $spacing 0;
	display: flex;
	align-items: center;

	.icon {
		height: 44px;
		margin-right: 2 * $spacing;
	}

	.input-wrapper {
		width: 100%;

		&::v-deep {
			.textarea {
				resize: vertical;
			}

			.input,
			.textarea {
				display: block;
				width: 100%;
				flex: 0;
			}
		}
	}
}
</style>
