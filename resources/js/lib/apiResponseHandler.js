import { toast } from "vue3-toastify";

export function handleResponse(response) {
    if (response?.data?.message?.status) {
        toast(response.data.message.description || "Unknown error!", {
            type: response.data.message.status,
        });
    }
}

export function handleError(error) {
    console.log(error);

    if (error?.response?.data?.message) {
        console.log(error);
        toast(error.response.data.message || "Unknown error!", {
            type: "danger",
        });
    } else {
        toast("An unexpected error occurred.", { type: "danger" });
    }
}

export function handleValidationMessage(error) {
    if (error?.response?.data?.errors) {
        // Map over each field to get the first message
        return Object.fromEntries(
            Object.entries(error.response.data.errors).map(
                ([field, messages]) => [field, messages[0]],
            ),
        );
    }
    return null;
}
