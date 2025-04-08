import { Link, useForm } from "@inertiajs/react";
import Authenticated from "../../../Layouts/Authenticated";

const CreateForm = () => {
    const { data, setData, post, processing, errors } = useForm({
        name: "",
        description: ""
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route("swift-auth.store"));
    };

    const handleCancel = () => {
        window.history.back();
    };

    return (
        <>
            <Head title="New user" />

            <div className="max-w-md mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
                <h2 className="text-2xl font-bold text-center mb-4">Agregar rol</h2>

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
                            Descripción
                        </label>
                        <input
                            type="text"
                            name="description"
                            value={data.description}
                            onChange={(e) => setData("description", e.target.value)}
                            className="w-full border rounded px-3 py-2 mt-1"
                            required
                        />
                        {errors.description && (
                            <p className="text-gray-500 text-sm">{errors.description}</p>
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

CreateForm.layout = (page) => <Authenticated>{page}</Authenticated>;

export default CreateForm;
