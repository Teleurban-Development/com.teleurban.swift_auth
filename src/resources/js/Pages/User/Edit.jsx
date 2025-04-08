import { Head, useForm } from "@inertiajs/react";
import { FormEvent, ReactNode } from "react";
import Authenticated from "../../Layouts/Authenticated";

const EditForm = ({ user }) => {
    const { data, setData, put, processing, errors } = useForm({
        name: user.name,
        email: user.email,

    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route("swift-auth.user.update", user.id), {
            onError: (errors) => alert(JSON.stringify(errors)),
        });
    };

    const handleCancel = () => {
        window.history.back();
    };

    return (
        <>
            <Head title="Editar usuario" />
            <div className="max-w-md mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
                <h2 className="text-2xl font-bold text-center mb-4">Editar usuario</h2>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium">Nombre</label>
                        <input
                            type="text"
                            name="name"
                            value={data.name}
                            onChange={(e) => setData("name", e.target.value)}
                            className="w-full border rounded px-3 py-2 mt-1"
                            required
                        />
                        {errors.name && (
                            <p className="text-gray-500 text-sm">{errors.name}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium">
                            Correo electrónico
                        </label>
                        <input
                            type="email"
                            name="email"
                            value={data.email}
                            onChange={(e) => setData("email", e.target.value)}
                            className="w-full border rounded px-3 py-2 mt-1"
                            required
                        />
                        {errors.email && (
                            <p className="text-gray-500 text-sm">{errors.email}</p>
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

EditForm.layout = (page) => <Authenticated>{page}</Authenticated>;

export default EditForm;
