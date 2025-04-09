import { Head, useForm } from "@inertiajs/react";
import { FormEvent, ReactNode } from "react";
import Authenticated from "../../../layouts/Authenticated";

type EditFormProps = {
    role: {
        id: number;
        name: string;
        description: string;
    };
};

const EditForm = ({ role }: EditFormProps) => {
    const { data, setData, put, processing, errors } = useForm({
        name: role.name,
        description: role.description,
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        put(route("swift-auth.user.role.update", role.id), {
            onError: (errors) => alert(JSON.stringify(errors)),
        });
    };

    const handleCancel = () => {
        window.history.back();
    };

    return (
        <>
            <Head title="Editar rol" />
            <div className="max-w-md mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
                <h2 className="text-2xl font-bold text-center mb-4">
                    Editar rol
                </h2>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium">
                            Nombre
                        </label>
                        <input
                            type="text"
                            name="name"
                            value={data.name}
                            onChange={(e) => setData("name", e.target.value)}
                            className="w-full border rounded px-3 py-2 mt-1"
                            required
                        />
                        {errors.name && (
                            <p className="text-gray-500 text-sm">
                                {errors.name}
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium">
                            Descripción
                        </label>
                        <input
                            type="text"
                            name="description"
                            value={data.description}
                            onChange={(e) =>
                                setData("description", e.target.value)
                            }
                            className="w-full border rounded px-3 py-2 mt-1"
                            required
                        />
                        {errors.description && (
                            <p className="text-gray-500 text-sm">
                                {errors.description}
                            </p>
                        )}
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

EditForm.layout = (page: ReactNode) => <Authenticated>{page}</Authenticated>;

export default EditForm;
