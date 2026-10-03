# khartasia_pdf

Export PDF d'une fiche de plante : ses noms vernaculaires et leurs attestations (mPDF).

    /node/{nid}/dossier-pdf                       version publique : contenus publiés seulement
    /node/{nid}/dossier-pdf?mode=relecture        relecture : contenus non publiés et notes de travail, filigrane
                                                  (permission « Voir le dossier PDF en relecture »)
    ...&lang=en                                   libellés en anglais ; les énoncés viennent du champ « Énoncé (anglais) » de
                                                  l'attestation, ou, à défaut, du français (signalé). Même règle pour les notes de
                                                  travail (champ « Note de travail (anglais) »). Les noms restent tels qu'enregistrés.
    ...&langue=tibétain                           un seul groupe de langue
    ...&telecharger=1                             téléchargement au lieu de l'affichage

Réglages sans configuration : le copyright est la constante `Labels::COPYRIGHT` ; les libellés sont dans `Labels`.
Écritures non latines (chinois, japonais, coréen, tibétain, dévanagari) : polices embarquées par mPDF, aucune à installer.
