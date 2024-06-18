<template>
    <PanelItem :index="index" :field="field">
        <template #value>
            <FormKeyValueTable
                v-if="fieldValues.length > 0"
                :edit-mode="false"
                class="overflow-hidden"
            >
                <FormKeyValueHeader
                    :key-label="__('Locale')"
                    :value-label="__('Translation')"
                />

                <div
                    class="bg-gray-50 dark:bg-gray-700 overflow-hidden key-value-items"
                >
                    <FormKeyValueItem
                        v-for="(item, index) in fieldValues"
                        :index="index"
                        :item="item"
                        :disabled="true"
                        :key="item.key"
                    />
                </div>
            </FormKeyValueTable>
        </template>
    </PanelItem>
</template>

<script>
import { Localization } from 'laravel-nova'

export default {
    props: ['index', 'resource', 'resourceName', 'resourceId', 'field'],

    mixins: [Localization],

    computed: {
        fieldComponent() {
            return this.field.component || 'DetailField';
        },

        fieldValues() {
            return Object.entries(this.field.value || {}).map(([key, value]) => ({
                key: `${key}`,
                value,
            }));
        },
    },
}
</script>
