import CreateAffiliation from '@/pages/affiliation/create';
import type { AffiliationFormProps } from '@/pages/affiliation/create';

export default function CreateAdministrativeAffiliation(
    props: AffiliationFormProps,
) {
    return <CreateAffiliation {...props} administrative />;
}

CreateAdministrativeAffiliation.layout = {
    breadcrumbs: [
        {
            title: 'Afiliar',
            href: '/administracion/afiliar',
        },
    ],
};
