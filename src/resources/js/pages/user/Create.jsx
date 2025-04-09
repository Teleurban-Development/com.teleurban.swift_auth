import { Link, useForm } from "@inertiajs/react";
import Authenticated from "../../layouts/Authenticated";

const CreateForm = () => {
    const { data, setData, post, processing, errors } = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
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
                <h2 className="text-2xl font-bold text-center mb-4">Agregar usuario</h2>

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

                    <div>
                        <label className="block text-sm font-medium">
                            Contraseña
                        </label>
                        <input
                            type="password"
                            name="password"
                            value={data.password}
                            onChange={(e) => setData("password", e.target.value)}
                            className="w-full border rounded px-3 py-2 mt-1"
                            required
                        />
                        {errors.password && (
                            <p className="text-gray-500 text-sm">
                                {errors.password}
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium">
                            Confirmar contraseña
                        </label>
                        <input
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData("password_confirmation", e.target.value)
                            }
                            className="w-full border rounded px-3 py-2 mt-1"
                            required
                        />
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
