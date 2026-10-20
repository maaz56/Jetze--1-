import "flowbite";
import { createApp, defineAsyncComponent } from "vue";
import "./bootstrap";
import router from "./config/router";
import store from "./config/store";
// root component
import { createPinia } from "pinia";
import Vue3Toasity from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import App from "./App.vue";
import i18n from "./services/i18n";

const app = createApp(App);
const pinia = createPinia();

document.title = "Cheap flight, Best rate hotel booking and holidays trip";

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
// The editor is used only by admin CMS screens. Loading it asynchronously keeps
// its sizeable JavaScript and CSS out of the public home-page download.
app.component("QuillEditor", defineAsyncComponent(async () => {
  const [{ QuillEditor }] = await Promise.all([
    import("@vueup/vue-quill"),
    import("@vueup/vue-quill/dist/vue-quill.snow.css"),
  ]);

  return QuillEditor;
}));
app.mount("#app");
