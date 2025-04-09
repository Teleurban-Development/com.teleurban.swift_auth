import { useForm, Head } from "@inertiajs/react";
import { FormEvent, ReactNode } from "react";
import Guest from "../../layouts/Guest";

const Reset = ({ token, email }: { token: string; email: string }) => {
    const { data, setData, post, processing, errors } = useForm({
        email,
        password: "",
        password_confirmation: "",
        token,
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route("swift-auth.password.update"));
    };

    return (
        <>
            <Head title="Reset password" />

            <div className="max-w-md mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
                <h2 className="text-2xl font-bold text-center mb-4">
                    Restablecer contraseña
                </h2>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <input type="hidden" name="token" value={token} />

                    <div>
                        <label className="block text-sm font-medium">
                            Correo electrónico
                        </label>
                        <input
                            type="email"
                            name="email"
                            value={data.email}
                            readOnly
                            className="w-full border rounded px-3 py-2 mt-1 bg-gray-100"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium">
                            Nueva contraseña
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

                    <button
                        type="submit"
                        className="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                        disabled={processing}
                    >
                        {processing
                            ? "Restableciendo..."
                            : "Restablecer contraseña"}
                    </button>
                </form>
            </div>
        </>
    );
};

Reset.layout = (page: ReactNode) => <Guest>{page}</Guest>;

export default Reset;
