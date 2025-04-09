import { Link, useForm, Head } from "@inertiajs/react";
import Authenticated from "../../../layouts/Authenticated";
import { FormEvent, ReactNode } from "react";

interface User {
    id: Number;
    name: string;
    email: string;
}

interface Role {
    id: Number;
    name: string;
    description: string;
}

interface AssignFormProps {
    users: User[];
    roles: Role[];
}

const AssignForm = ({ users, roles }: AssignFormProps) => {
    const { data, setData, post, processing, errors } = useForm({
        user: "",
        role: "",
    });

    const handleUserChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setData("user", e.target.value);
    };

    const handleRoleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setData("role", e.target.value);
    };

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route("swift-auth.user.role.assign"));
    };

    const handleCancel = () => {
        window.history.back();
    };

    return (
        <>
            <Head title="Asignar rol" />

            <div className="max-w-md mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
                <h2 className="text-2xl font-bold text-center mb-4">
                    Asignar rol
                </h2>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium">
                            Usuario
                        </label>
                        <select id="users" onChange={handleUserChange}>
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium">Rol</label>
                        <select id="roles" onChange={handleRoleChange}>
                            {roles.map((role) => (
                                <option key={role.id} value={role.id}>
                                    {role.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex justify-between items-center">
                        <button
                            type="button"
                            className="bg-transparent hover:bg-gray-500 text-gray-700 font-semibold hover:text-white py-2 px-4 border border-gray-500 hover:border-transparent rounded"
                            onClick={handleCancel}
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            className="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600"
                            disabled={processing}
                        >
                            {processing ? "Enviando..." : "Guardar"}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
};

CreateForm.layout = (page: ReactNode) => <Authenticated>{page}</Authenticated>;

export default AssignForm;
