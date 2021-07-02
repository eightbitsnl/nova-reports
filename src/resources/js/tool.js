Nova.booting((Vue, router, store) => {
	Vue.component('index-querybuilder-field', require('./components/nova/IndexField'))
	Vue.component('detail-querybuilder-field', require('./components/nova/DetailField'))
	Vue.component('form-querybuilder-field', require('./components/nova/FormField'))
  })
  