<script setup>
import { Button } from "@/components/ui/button";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import Input from "@/components/ui/input/Input.vue";
import {
    Pencil,
    Trash2,
    Plus,
    Shield,
    X,
    Check
} from "lucide-vue-next";
import { toast } from "vue3-toastify";
import { onMounted, ref, reactive } from "vue";
import axios from "axios";
import Spinner from "@/components/common/Spinner.vue";

const roles = ref([]);
const permissions = ref([]);
const isLoading = ref(false);
const isDialogOpen = ref(false);
const isEditing = ref(false);
const currentRoleId = ref(null);

const form = reactive({
    name: '',
    permissions: []
});

const fetchRoles = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/roles');
        roles.value = response.data.data;
    } catch (error) {
        toast.error("Failed to fetch roles");
    } finally {
        isLoading.value = false;
    }
};

const fetchPermissions = async () => {
    try {
        const response = await axios.get('/api/permissions');
        permissions.value = response.data.data;
    } catch (error) {
        toast.error("Failed to fetch permissions");
    }
};

const openAddDialog = () => {
    isEditing.value = false;
    currentRoleId.value = null;
    form.name = '';
    form.permissions = [];
    isDialogOpen.value = true;
};

const openEditDialog = (role) => {
    isEditing.value = true;
    currentRoleId.value = role.id;
    form.name = role.name;
    form.permissions = role.permissions.map(p => p.name);
    isDialogOpen.value = true;
};

const togglePermission = (permName) => {
    const index = form.permissions.indexOf(permName);
    if (index === -1) {
        form.permissions.push(permName);
    } else {
        form.permissions.splice(index, 1);
    }
};

const saveRole = async () => {
    if (!form.name) {
        toast.warning("Role name is required");
        return;
    }

    isLoading.value = true;
    try {
        if (isEditing.value) {
            await axios.put(`/api/roles/${currentRoleId.value}`, form);
            toast.success("Role updated successfully");
        } else {
            await axios.post('/api/roles', form);
            toast.success("Role created successfully");
        }
        isDialogOpen.value = false;
        fetchRoles();
    } catch (error) {
        toast.error(error.response?.data?.message?.description || "Failed to save role");
    } finally {
        isLoading.value = false;
    }
};

const deleteRole = async (id) => {
    if (!confirm("Are you sure you want to delete this role?")) return;
    
    try {
        await axios.delete(`/api/roles/${id}`);
        toast.success("Role deleted successfully");
        fetchRoles();
    } catch (error) {
        toast.error(error.response?.data?.message?.description || "Failed to delete role");
    }
};

onMounted(() => {
    fetchRoles();
    fetchPermissions();
});
</script>

<template>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <span class="block text-3xl font-medium leading-none tracking-tight text-gray-900">
                Roles & Permissions
            </span>
        </div>
        <Button @click="openAddDialog" class="flex items-center">
            <Plus class="w-4 h-4 mr-2" /> Add Role
        </Button>
    </div>

    <div class="bg-white p-8 rounded-lg border shadow-sm">
        <section v-if="isLoading && roles.length === 0" class="p-24 flex items-center justify-center">
            <Spinner />
        </section>

        <section v-else>
            <Table>
                <TableHeader class="bg-primary">
                    <TableRow>
                        <TableHead scope="col" class="px-4 py-3 text-white">Role Name</TableHead>
                        <TableHead scope="col" class="px-4 py-3 text-white">Permissions</TableHead>
                        <TableHead scope="col" class="px-4 py-3 text-white">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="role in roles" :key="role.id" class="border-b hover:bg-gray-50">
                        <TableCell class="px-4 py-4 font-medium capitalize">
                            {{ role.name }}
                        </TableCell>
                        <TableCell class="px-4 py-4">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="perm in role.permissions" :key="perm.id" 
                                    class="bg-blue-50 text-blue-700 text-[10px] px-2 py-0.5 rounded-full border border-blue-100">
                                    {{ perm.name }}
                                </span>
                                <span v-if="role.permissions.length === 0" class="text-gray-400 text-xs italic">
                                    No permissions assigned
                                </span>
                            </div>
                        </TableCell>
                        <TableCell class="px-4 py-4 space-x-2 whitespace-nowrap">
                            <button @click="openEditDialog(role)"
                                class="inline-flex items-center justify-center rounded-md text-sm font-medium border border-input bg-background hover:bg-gray-100 h-8 px-3">
                                <Pencil class="w-4 h-4 mr-2" /> Edit
                            </button>
                            <button v-if="role.name !== 'admin'" @click="deleteRole(role.id)"
                                class="inline-flex items-center justify-center rounded-md text-sm font-medium border border-input bg-background hover:bg-red-50 text-destructive h-8 px-3">
                                <Trash2 class="w-4 h-4 mr-2" /> Delete
                            </button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>
    </div>

    <!-- Add/Edit Role Dialog -->
    <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
        <DialogContent class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Edit Role' : 'Add New Role' }}</DialogTitle>
                <DialogDescription>
                    Define the role name and assign permissions for this role.
                </DialogDescription>
            </DialogHeader>
            
            <div class="grid gap-4 py-4">
                <div class="grid gap-2">
                    <label for="name" class="text-sm font-medium text-gray-700">Role Name</label>
                    <Input id="name" v-model="form.name" placeholder="e.g. Manager" class="col-span-3" />
                </div>
                
                <div class="grid gap-2 mt-4">
                    <label class="text-sm font-medium text-gray-700 mb-2">Permissions</label>
                    <div class="grid grid-cols-2 gap-3 max-h-[300px] overflow-y-auto p-1">
                        <div v-for="perm in permissions" :key="perm.id" 
                            @click="togglePermission(perm.name)"
                            class="flex items-center p-2 rounded border cursor-pointer transition-all"
                            :class="form.permissions.includes(perm.name) ? 'bg-primary/10 border-primary shadow-sm' : 'border-gray-200 hover:border-primary/50'">
                            <div class="w-4 h-4 rounded border flex items-center justify-center mr-3"
                                :class="form.permissions.includes(perm.name) ? 'bg-primary border-primary' : 'bg-white border-gray-300'">
                                <Check v-if="form.permissions.includes(perm.name)" class="w-3 h-3 text-white" />
                            </div>
                            <span class="text-xs capitalize">{{ perm.name.replace(/-/g, ' ') }}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <DialogFooter>
                <Button variant="outline" @click="isDialogOpen = false">Cancel</Button>
                <Button @click="saveRole" :disabled="isLoading">
                    {{ isLoading ? 'Saving...' : 'Save Role' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
