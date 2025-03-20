import { ReactNode } from "react";
import Authenticated from "../../Layouts/Authenticated";

const Index = ({ users }) => {
    return (
        <div className="max-w-4xl mx-auto mt-10 bg-white p-6 rounded-lg shadow-md">
            <h2 className="text-2xl font-bold text-center mb-4">Usuarios</h2>
            <table className="w-full border-collapse border border-gray-200">
                <thead>
                    <tr className="bg-gray-100">
                        <th className="border p-2">Nombre</th>
                        <th className="border p-2">Correo electrónico</th>
                    </tr>
                </thead>
                <tbody>
                    {users.length > 0 ? (
                        users.map((user) => (
                            <tr
                                key={user.id}
                                className="text-center hover:bg-gray-50"
                            >
                                <td className="border p-2">{user.name}</td>
                                <td className="border p-2">{user.email}</td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td
                                colSpan={2}
                                className="border p-4 text-center text-gray-500"
                            >
                                No hay usuarios autenticados.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
};

Index.layout = (page) => <Authenticated>{page}</Authenticated>;

export default Index;
