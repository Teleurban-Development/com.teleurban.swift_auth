import { Navbar } from "@Components/Navbar/Navbar";

export default function Authenticated({
    user,
    children,
}: {
    user?: { name: string };
    children: React.ReactNode;
}) {
    return (
        <div>
            <Navbar user={user} />
            <main className="max-w-3xl mx-auto mt-6">{children}</main>
        </div>
    );
}
