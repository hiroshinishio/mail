<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<div class="section">
		<ul>
			<NcListItem v-for="filter in filters"
				:key="filter.id"
				:name="filter.name"
				:compact="true"
				@click="updateFilter(filter)" />
		</ul>
		<NcButton class="app-settings-button"
			type="secondary"
			:aria-label="t('mail', 'New filter')"
			@click.prevent.stop="createFilter">
			{{ t('mail', 'New filter') }}
		</NcButton>
		<MailFilterModal v-if="currentFilter"
			:filter="currentFilter"
			:account="account"
			:loading="loading"
			@store-filter="storeFilter"
			@close="closeModal" />
	</div>
</template>

<script>
import { NcButton as ButtonVue, NcLoadingIcon as IconLoading, NcActionButton, NcListItem, NcButton } from '@nextcloud/vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconLock from 'vue-material-design-icons/Lock.vue'
import MailFilterModal from './MailFilterModal.vue'
import { randomId } from '../util/randomId'
import logger from '../logger'
import { mapStores } from 'pinia'
import useMailFilterStore from '../store/mailFilterStore'

export default {
	name: 'MailFilters',
	components: {
		IconLock,
		NcButton,
		ButtonVue,
		IconLoading,
		IconCheck,
		NcListItem,
		NcActionButton,
		MailFilterModal,
	},
	props: {
		account: {
			type: Object,
			required: true,
		},
	},
	data() {
		return {
			showModal: false,
			script: '',
			loading: true,
			errorMessage: '',
			currentFilter: null,
		}
	},
	computed: {
		...mapStores(useMailFilterStore),
		filters() {
			return this.mailFilterStore.filters
		},
		scriptData() {
			return this.$store.getters.getActiveSieveScript(this.account.id)
		},
	},
	watch: {
		scriptData: {
			immediate: true,
			handler(scriptData) {
				if (!scriptData) {
					return
				}

				this.script = scriptData.script
				this.loading = false
			},
		},
	},
	async mounted() {
		await this.mailFilterStore.fetch(this.account.id)
	},
	methods: {
		createFilter() {
			this.currentFilter = {
				id: randomId(),
				name: t('mail', 'New filter'),
				enable: false,
				operator: 'allof',
				tests: [],
				actions: [],
			}
			this.showModal = true
		},
		updateFilter(filter) {
			this.currentFilter = filter
			this.showModal = true
		},
		async storeFilter(filter) {
			this.loading = true

			this.mailFilterStore.$patch((state) => {
				const index = state.filters.findIndex((item) => item.id === filter.id)
				logger.debug('store filter', { filter, index })

				if (index === -1) {
					state.filters.push(filter)
				} else {
					state.filters[index] = filter
				}
			})

			try {
				await this.mailFilterStore.update(this.account.id)
			} catch (e) {
				// TODO error toast
			} finally {
				this.loading = false
			}

			await this.$store.dispatch('fetchActiveSieveScript', { accountId: this.account.id })
		},
		closeModal() {
			this.showModal = false
			this.currentFilter = null
		},
	},
}
</script>

<style lang="scss" scoped>
.section {
	display: block;
	padding: 0;
	margin-bottom: 23px;
}

textarea {
	width: 100%;
}

.primary {
	padding-left: 26px;
	background-position: 6px;
	color: var(--color-main-background);

	&:after {
		 left: 14px;
	 }
}
</style>
