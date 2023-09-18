import IndexField from "./components/nova/IndexField";
import DetailField from "./components/nova/DetailField";
import FormField from "./components/nova/FormField";

Nova.booting((app, store) => {
    app.component("index-querybuilder-field", IndexField);
    app.component("detail-querybuilder-field", DetailField);
    app.component("form-querybuilder-field", FormField);
});
