import IndexField from './components/nova/IndexField.vue';
import DetailField from './components/nova/DetailField.vue';
import FormField from './components/nova/FormField.vue';


Nova.booting((Vue, router, store) => {
	Vue.component('index-querybuilder-field', IndexField)
	Vue.component('detail-querybuilder-field', DetailField)
	Vue.component('form-querybuilder-field', FormField)
  })
  