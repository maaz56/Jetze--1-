import apiService from "@/config/axios";
import { defineStore } from "pinia";
import { toast } from "vue3-toastify";

export const useFileUploaderStore = defineStore("fileUploader", {
    state: () => ({
        files: JSON.parse(localStorage.getItem("uploads")) || [],
        isLoading: false,
        validationErrors: null,
    }),
    getters: {
        getFiles: (state) => state.files,
        getIsLoading: (state) => state.isLoading,
        getValidationErrors: (state) => state.validationErrors,
    },
    actions: {
        async fetchUploadedFiles(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/uploads", {
                    params: params,
                });
                //console.log(JSON.stringify(response.data));
                this.files = response.data; // Directly set files from response
                localStorage.setItem("uploads", JSON.stringify(this.files)); // Update localStorage
            } catch (error) {
                //console.log(error);
                toast(error, {
                    theme: "dark",
                    type: "error",
                    dangerouslyHTMLString: true,
                });
            } finally {
                this.isLoading = false;
            }
        },

        async uploadFile(formData) {
            this.isLoading = true;
            try {
                const response = await apiService.post("/uploads", formData, {
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                });
                //console.log(JSON.stringify(response.data));
                await this.fetchUploadedFiles(); // Refresh the uploaded files
                toast("File Uploaded.", {
                    theme: "dark",
                    type: "success",
                    dangerouslyHTMLString: true,
                });
            } catch (error) {
                //console.log(error);
                toast(error, {
                    theme: "dark",
                    type: "error",
                    dangerouslyHTMLString: true,
                });
            } finally {
                this.isLoading = false;
            }
        },

        async deleteUploadedFile(params) {
            //console.log(params);
            this.isLoading = true;
            try {
                const response = await apiService.delete(
                    `/uploads/${params.file_name}`,
                );
                //console.log(JSON.stringify(response.data));
                await this.fetchUploadedFiles(); // Refresh the uploaded files
                this.files = this.files.filter(
                    (file) => file.name !== params.name,
                ); // Update files directly
                localStorage.setItem("uploads", JSON.stringify(this.files)); // Update localStorage
                toast("File deleted successfully.", {
                    theme: "dark",
                    type: "success",
                    dangerouslyHTMLString: true,
                });
            } catch (error) {
                //console.log(error);
                toast(error, {
                    theme: "dark",
                    type: "error",
                    dangerouslyHTMLString: true,
                });
            } finally {
                this.isLoading = false;
            }
        },
    },
});
