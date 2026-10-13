import { Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Props = {
    currentUrl?: string | null;
    error?: string;
    allowRemoval?: boolean;
};

export function LogoUploadField({
    currentUrl = null,
    error,
    allowRemoval = false,
}: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [removing, setRemoving] = useState(false);

    const shown = preview ?? (removing ? null : currentUrl);

    const pick = (file: File | undefined) => {
        if (!file) {
            return;
        }

        setRemoving(false);
        setPreview(URL.createObjectURL(file));
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor="logo">Logo de l organisation</Label>
            <div className="flex items-center gap-4">
                <span className="border-border bg-surface flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border">
                    {shown ? (
                        <img
                            src={shown}
                            alt="Apercu du logo"
                            className="size-full object-contain"
                        />
                    ) : (
                        <AppLogoIcon className="text-primary dark:text-accent size-8" />
                    )}
                </span>
                <div className="min-w-0">
                    <input
                        ref={inputRef}
                        id="logo"
                        name="logo"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        className="sr-only"
                        onChange={(event) => pick(event.target.files?.[0])}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-9"
                            onClick={() => inputRef.current?.click()}
                        >
                            <Upload className="size-4" />
                            Choisir un fichier
                        </Button>
                        {allowRemoval && currentUrl && !preview && (
                            <label className="text-text-secondary flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="remove_logo"
                                    value="1"
                                    checked={removing}
                                    onChange={(event) =>
                                        setRemoving(event.target.checked)
                                    }
                                    className="accent-danger size-4"
                                />
                                <Trash2 className="text-danger size-3.5" />
                                Retirer le logo
                            </label>
                        )}
                    </div>
                    <p className="text-text-muted mt-2 text-xs">
                        PNG, JPG ou WebP, 2 Mo maximum, au moins 64x64 pixels.
                        Sans logo, la marque Aeterna est utilisee.
                    </p>
                </div>
            </div>
            <InputError message={error} />
        </div>
    );
}
