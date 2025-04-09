import { Link, useForm, Head } from "@inertiajs/react";
import Guest from "../layouts/Guest";

const RegisterForm = () => {
    const { data, setData, post, processing, errors } = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route("swift-auth.store"),  {
            onError: (error) => {
                console.log(error);
            },
        });
    };

    return (
        <>
            <Head title="Register" />

            <div className="max-w-md mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
                <h2 className="text-2xl font-bold text-center mb-4">Registrarse</h2>

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
                            <p className="text-red-500 text-sm">{errors.name}</p>
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
                            <p className="text-red-500 text-sm">{errors.email}</p>
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
                            <p className="text-red-500 text-sm">
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
                        <Link
                            href={route("swift-auth.login")}
                            className="text-sm text-blue-500"
                        >
                            ¿Ya tienes cuenta? Inicia sesión
                        </Link>
                        <button
                            type="submit"
                            className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                            disabled={processing}
                        >
                            {processing ? "Registrando..." : "Registrarse"}
                        </button>
                    </div>
                </form>
            </div>
        </>
    );
};

RegisterForm.layout = (page) => <Guest>{page}</Guest>;

export default RegisterForm;
