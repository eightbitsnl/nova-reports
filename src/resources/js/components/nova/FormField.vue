<template>
    <DefaultField :field="field" :errors="errors" :show-help-text="showHelpText" :full-width-content="fullWidthContent">
        <template #field>
            <div>
                <div class="mb-4 border rounded bg-gray-50">
                    <div class="p-4 border-b">
                        <strong>Query</strong>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="flex flex-col">
                            <label class="mt-1 mb-3"><strong>Select</strong></label>
                            <select class="rounded form-control form-input-bordered" name="" id="" v-model="value.entrypoint">
                                <option v-for="entrypoint in entrypoints" :value="entrypoint.value">{{ entrypoint.label }}</option>
                            </select>
                        </div>

                        <div class="querybuilderfield-wrapper">
                            <label class="mt-1"><strong>Filter</strong></label>
                            <vue-query-builder :rules="rules" v-model="value.query" />
                        </div>

                        <div v-if="available_relations.length">
                            <label class="mt-1"><strong>Relations</strong></label>

                            <label class="flex items-center mt-3 space-x-2 select-none" v-for="(relation, relation_i) in available_relations">
                                <input class="checkbox" type="checkbox" :value="relation" :id="'relations' + relation_i" v-model="value.loadrelation" />
                                <span>
                                    {{ relation }}
                                </span>
                            </label>
                        </div>

                        <div>
                            <label class="mt-1"><strong>Export Fields</strong></label>

                            <div class="flex flex-wrap mt-3 -m-1">
                                <div class="flex pr-4" v-for="(group_fields, group_name) in exportable_groups">
                                    <div class="mb-4 bg-white border rounded">
                                        <div class="p-4 bg-gray-200">
                                            <strong>{{ group_name }}</strong>
                                        </div>

                                        <div class="p-4">
                                            <label class="flex items-center mt-3 space-x-2 select-none" v-for="(field_name, field_value) in group_fields['fields']">
                                                <input type="checkbox" class="checkbox" :value="group_name + '.' + field_value" v-model="value.export_fields" />
                                                <span>{{ field_name }}</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4 border rounded bg-gray-50">
                    <div class="p-4 border-b">
                        <strong>Preview</strong>
                    </div>
                    <template v-if="preview">
                        <div class="w-full overflow-auto" style="max-height: 500px">
                            <table class="table-auto">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th v-for="label in preview.headings" v-html="label.split('\n').join('&nbsp;')" class="p-2 tracking-wide text-left text-gray-500 uppercase whitespace-nowrap text-xxs"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="item in preview.items">
                                        <td v-for="(label, key) in preview.headings" class="p-2">
                                            {{ item[key] }}
                                        </td>
                                    </tr>
                                    <tr v-if="preview.count > 1">
                                        <td v-for="(label, key) in preview.headings" class="p-2">...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>
            <div class="overflow-scroll"></div>
        </template>
    </DefaultField>
</template>

<script>
import { FormField, HandlesValidationErrors } from "laravel-nova";
import VueQueryBuilder from "vue-query-builder";
import { toRaw } from "vue";

var _ = require("lodash");

export default {
    mixins: [FormField, HandlesValidationErrors],

    props: ["resourceName", "resourceId", "field"],

    components: {
        VueQueryBuilder,
    },

    data: function () {
        return {
            entrypoints: [],
            preview: "...",
            rules: [],
        };
    },

    computed: {
        available_relations: function () {
            return this.value && this.value.entrypoint && !_.isEmpty(this.entrypoints) ? this.entrypoints[this.value.entrypoint].available_relations : [];
        },

        exportable_groups: function () {
            // get the groups based on the entrypoint
            var groups = this.value && this.value.entrypoint && !_.isEmpty(this.entrypoints) ? this.entrypoints[this.value.entrypoint].exportable_fields : {};

            // filter only relevant groups
            var selected_relations = this.value.loadrelation;
            return _.pickBy(groups, function (data, name) {
                return data["type"] == "main" || (data["type"] == "relation" && selected_relations.includes(name));
            });
        },

        exportable_groupnames: function () {
            return _.keys(this.exportable_groups);
        },
    },

    watch: {
        "value.entrypoint": {
            deep: true,
            handler: function (val, oldVal) {
                // when entry point changes

                // update rules, based on the selected entrypoint
                this.rules = val && !_.isEmpty(this.entrypoints) ? this.entrypoints[val].rules : [];

                // reset loadrelation and query
                if (!this.value.query || typeof oldVal != "undefined") {
                    var defaults = this.getDefaultValue();
                    this.value.loadrelation = defaults.loadrelation;
                    this.value.export_fields = defaults.export_fields;
                    this.value.query = defaults.query;
                }
            },
        },

        // 'value.relations': {
        "value.loadrelation": {
            deep: true,
            handler: function (val, oldVal) {
                if (oldVal == "") return;
                // if a relation was DESELECTED
                if (oldVal && val.length < oldVal.length) {
                    // remove unselected relations from value.export_fields
                    var exportable_groupnames = this.exportable_groupnames;
                    this.value.export_fields = _.toArray(
                        _.pickBy(this.value.export_fields, function (val) {
                            var parts = val.split(".");
                            return exportable_groupnames.includes(parts[0]);
                        })
                    );
                }
                this.updatePreview();
            },
        },

        "value.query": {
            deep: true,
            handler: function (val, oldVal) {
                console.log("WATCH value.query", { val: toRaw(val), oldVal: toRaw(oldVal) });
                this.updatePreview(1000);
            },
        },

        "value.export_fields": {
            deep: true,
            handler: function (val, oldVal) {
                console.log("WATCH value.export_fields", { val: toRaw(val), oldVal: toRaw(oldVal) });
                this.updatePreview(1000);
            },
        },
    },

    methods: {
        /**
         * The default value for the query builder field
         */
        getDefaultValue() {
            return {
                entrypoint: null,
                loadrelation: [],
                export_fields: [],
                query: {
                    logicalOperator: "any",
                    children: [],
                },
            };
        },

        /*
         * Set the initial, internal value for the field.
         */
        setInitialValue() {
            // this.value = this.field.value || this.getDefaultValue();
            this.value = {};
            this.fetchInit();
        },

        /**
         * Fill the given FormData object with the field's internal value.
         */
        fill(formData) {
            formData.append(this.fieldAttribute, JSON.stringify(this.value) || null);
        },

        updatePreview(delay = 0) {
            var vm = this;

            clearTimeout(vm.updatePreviewTimeOut);
            vm.updatePreviewTimeOut = setTimeout(function () {
                var postdata = vm.value;

                if (postdata.entrypoint == null) return;

                Nova.request()
                    .post("/nova-vendor/eightbitsnl/nova-reports/webpreview" + (typeof vm.resourceId == "undefined" ? "" : "/" + vm.resourceId), postdata)
                    .then((response) => {
                        vm.preview = response.data;
                        // vm.codemirror.getDoc().setValue(JSON.stringify(response.data, null, 2));
                    })
                    .catch(function (error) {
                        console.log(error.toJSON());
                    });
            }, delay);
        },

        fetchInit() {
            var vm = this;
            Nova.request()
                .get("/nova-vendor/eightbitsnl/nova-reports/init" + (typeof this.resourceId == "undefined" ? "" : "/" + this.resourceId))
                .then((response) => {
                    console.log("response", response);
                    // set a default value for value.entrypoint
                    if (!vm.field.value.entrypoint) {
                        vm.field.value.entrypoint = _.keys(response.data.entrypoints)[0];
                    }

                    // update list of available entrypoints
                    vm.entrypoints = response.data.entrypoints;

                    // set initial value
                    vm.value.entrypoint = response.data.entrypoint;
                    vm.value.query = response.data.query;
                    vm.value.export_fields = response.data.export_fields;
                    vm.value.loadrelation = response.data.loadrelation;
                    // vm.value = vm.field.value || vm.getDefaultValue();
                });
        },
    },
};
</script>

<style lang="scss">
.querybuilderfield-wrapper {
    @import "~bootstrap/scss/bootstrap.scss";
}
@import "~vue-query-builder/dist/VueQueryBuilder.css";

div .vue-query-builder {
    @apply border rounded bg-white mb-4  #{!important};
}

.querybuilderfield-wrapper {
    .form-select {
        background-color: white !important;
        border-radius: 0.25rem !important;
        border: rgb(203, 213, 225) solid 1px !important;
    }
    .form-control {
        background-color: white !important;
        border-radius: 0.25rem !important;
        border: rgb(203, 213, 225) solid 1px !important;
    }
    select#vqb-match-type {
        background-color: white !important;
        border-radius: 0.25rem !important;
        border: rgb(203, 213, 225) solid 1px !important;
    }
    .card-header:first-child {
        padding: 16px !important;
        border-bottom: 1px !important;
        background-color: rgb(226, 232, 240) !important;
    }

    .vqb-group.depth-2,
    .vqb-group.depth-3,
    .vqb-rule {
        border-radius: 0.25rem !important;
        border-top: 1px solid rgba(0, 0, 0, 0.125) !important;
        border-right: 1px solid rgba(0, 0, 0, 0.125) !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.125) !important;
    }

    .btn {
        background-color: rgb(14, 165, 233) !important;
        border-radius: 0.25rem !important;
    }
}
</style>
