import { Link, useForm } from "@inertiajs/react";
import Guest from "../../Layouts/Guest";

const Email = () => {
    const { data, setData, post, processing, errors } = useForm({
        email: "",
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route("swift-auth.password.email"));
    };

    return (
        <div className="max-w-md mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
            <h2 className="text-2xl font-bold text-center mb-4">
                Recuperar contraseña
            </h2>
            <p className="text-sm text-gray-600 mb-4 text-center">
                Ingresa tu correo y te enviaremos un enlace para restablecer tu
                contraseña.
            </p>

            <form onSubmit={handleSubmit} className="space-y-4">
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

                <div className="flex justify-between items-center">
                    <Link
                        href={route("swift-auth.login")}
                        className="text-sm text-blue-500"
                    >
                        Volver al inicio de sesión
                    </Link>
                    <button
                        type="submit"
                        className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                        disabled={processing}
                    >
                        {processing ? "Enviando..." : "Enviar enlace"}
                    </button>
                </div>
            </form>
        </div>
    );
};

Email.layout = (page) => <Guest>{page}</Guest>;

export default Email;
