import { Navbar } from "@/components/navbar/Navbar";
import { usePage } from "@inertiajs/react";

export default function Authenticated({ children }) {
    const { auth } = usePage().props;
    return (
        <div>
            <Navbar user={auth?.user} />
            <main className="max-w-3xl mx-auto mt-6">{children}</main>
        </div>
    );
}
