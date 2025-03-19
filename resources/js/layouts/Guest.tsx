import { PropsWithChildren } from "react";

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="bg-background bg-fixed bg-contain bg-no-repeat bg-bottom bg-[url('/assets/images/background.png')]">
            <div className="min-h-screen flex flex-col items-center sm:justify-center sm:pt-0 pt-6">
                <div className="mt-6 w-full overflow-hidden px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg bg-background">
                    {children}
                </div>
            </div>
        </div>
    );
}
