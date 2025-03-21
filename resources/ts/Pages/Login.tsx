import { Link, useForm, Head } from "@inertiajs/react";
import { FormEvent, ReactNode } from "react";
import Guest from "../Layouts/Guest";

const LoginForm = () => {
    const { data, setData, post, processing, errors } = useForm({
        email: "",
        password: "",
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route("swift-auth.login.submit"));
    };

    return (
        <>
            <Head title="Login" />

            <div className="max-w-md mx-auto mt-10 bg-white px-6 pt-6  rounded-lg shadow-md">
                <h2 className="text-2xl font-bold text-center mb-4">
                    Iniciar sesión
                </h2>

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
                            autoFocus
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


                    <div className="">
                        <button
                            type="submit"
                            className="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 w-full rounded-lg"
                            disabled={processing}
                        >
                            {processing ? "Cargando..." : "Iniciar sesión"}
                        </button>
                        <div className="text-center">

                            <Link
                                href={route("swift-auth.password.request")}
                                className="text-sm text-blue-500"
                            >
                                ¿Olvidaste tu contraseña?
                            </Link>

                        </div>
                    </div>
                </form>
                <div className="text-center p-4">
                    <span>¿No tienes cuenta? </span>
                    <Link
                        href={route("swift-auth.register")}
                        className="text-sm text-blue-500"
                    >
                        Registrate
                    </Link>
                </div>
            </div>
        </>
    );
};

LoginForm.layout = (page: ReactNode) => <Guest>{page}</Guest>;

export default LoginForm;
