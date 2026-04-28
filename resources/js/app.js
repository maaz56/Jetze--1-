import "flowbite";
import { createApp } from "vue";
import "./bootstrap";
import router from "./config/router";
import store from "./config/store";
// root component
import { QuillEditor } from "@vueup/vue-quill";
import "@vueup/vue-quill/dist/vue-quill.snow.css";
import { createPinia } from "pinia";
import Vue3Toasity from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import App from "./App.vue";
import i18n from "./services/i18n";

const app = createApp(App);
const pinia = createPinia();

app.use(store);
app.use(pinia)
app.use(router);
app.use(i18n);
app.use(Vue3Toasity, {
  position: "bottom-center",
  autoClose: 2000,
  hideProgressBar: true,
  closeButton: false,
  clearOnUrlChange: false,
  newestOnTop: true,
  transition: "slide",
});
app.component("QuillEditor", QuillEditor);
app.mount("#app");
