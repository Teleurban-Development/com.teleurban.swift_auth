import { Navbar } from "@/Components/Navbar/Navbar";
export default function Authenticated({
    auth,
    children,
}: {
    auth?: {
        user:
        { name: string }
    };
    children: React.ReactNode;
}) {
    return (
        <div>
            <Navbar user={auth?.user} />
            <main className="max-w-3xl mx-auto mt-6">{children}</main>
        </div>
    );
}
