import { Navbar } from "@/components/navbar/Navbar";
import { usePage } from "@inertiajs/react";

interface Auth {
    user: {
        name: string;
    };
}

interface PageProps {
    auth?: Auth;
}


export default function Authenticated({
    children,
}: {
    children: React.ReactNode;
}) {
    const { auth } = usePage().props as PageProps;
    return (
        <div>
            <Navbar user={auth?.user} />
            <main className="max-w-3xl mx-auto mt-6">{children}</main>
        </div>
    );
}
