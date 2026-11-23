import "../css/app.css";
import "primeicons/primeicons.css";

import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createApp, h } from "vue";
import { ZiggyVue } from "../../vendor/tightenco/ziggy";

import PrimeVue from "primevue/config";
import Aura from "@primeuix/themes/aura";
import { definePreset } from "@primeuix/themes";
import ConfirmationService from "primevue/confirmationservice";
import ToastService from "primevue/toastservice";
import Avatar from "primevue/avatar";
import AutoComplete from "primevue/autocomplete";
import Badge from "primevue/badge";
import Button from "primevue/button";
import Card from "primevue/card";
import Checkbox from "primevue/checkbox";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import DataTable from "primevue/datatable";
import Dialog from "primevue/dialog";
import Divider from "primevue/divider";
import Drawer from "primevue/drawer";
import InputNumber from "primevue/inputnumber";
import InputText from "primevue/inputtext";
import Menu from "primevue/menu";
import Message from "primevue/message";
import Panel from "primevue/panel";
import ProgressBar from "primevue/progressbar";
import Select from "primevue/select";
import Skeleton from "primevue/skeleton";
import Tag from "primevue/tag";
import Textarea from "primevue/textarea";
import Toast from "primevue/toast";

const appName = import.meta.env.VITE_APP_NAME || "Payroll Workspace";
const PayrollPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: "{red.50}",
            100: "{red.100}",
            200: "{red.200}",
            300: "{red.300}",
            400: "{red.400}",
            500: "{red.500}",
            600: "{red.600}",
            700: "{red.700}",
            800: "{red.800}",
            900: "{red.900}",
            950: "{red.950}",
        },
    },
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob("./Pages/**/*.vue"),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, {
                theme: {
                    preset: PayrollPreset,
                    options: {
                        prefix: "p",
                        darkModeSelector: ".app-dark",
                        cssLayer: {
                            name: "primevue",
                            order: "theme, base, primevue, utilities",
                        },
                    },
                },
            })
            .use(ConfirmationService)
            .use(ToastService)
            .component("Avatar", Avatar)
            .component("AutoComplete", AutoComplete)
            .component("Badge", Badge)
            .component("Button", Button)
            .component("Card", Card)
            .component("Checkbox", Checkbox)
            .component("Column", Column)
            .component("ConfirmDialog", ConfirmDialog)
            .component("DataTable", DataTable)
            .component("Dialog", Dialog)
            .component("Divider", Divider)
            .component("Drawer", Drawer)
            .component("InputNumber", InputNumber)
            .component("InputText", InputText)
            .component("Menu", Menu)
            .component("Message", Message)
            .component("Panel", Panel)
            .component("ProgressBar", ProgressBar)
            .component("Select", Select)
            .component("Skeleton", Skeleton)
            .component("Tag", Tag)
            .component("Textarea", Textarea)
            .component("Toast", Toast)
            .mount(el);
    },
    progress: {
        color: "#4B5563",
    },
});
