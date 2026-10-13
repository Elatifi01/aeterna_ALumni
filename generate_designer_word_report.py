import os
import sys
import docx
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.oxml import OxmlElement, parse_xml
from docx.oxml.ns import nsdecls, qn

def build_designer_report():
    doc = docx.Document()

    # Couleurs du Design System Aeterna
    HEX_PRIMARY = "16375A"       # Bleu Nuit Impérial
    HEX_SECONDARY = "2E6F9E"     # Bleu Ardoise
    HEX_ACCENT = "D97706"        # Or / Ambre
    HEX_BG_LIGHT = "F8FAFC"      # Blanc Perle / Ardoise très clair
    HEX_BORDER = "CBD5E1"        # Gris bordure douce
    HEX_TEXT = "1E293B"          # Gris très foncé pour lecture
    HEX_MUTED = "64748B"         # Gris secondaire

    COLOR_PRIMARY = RGBColor(22, 55, 90)
    COLOR_SECONDARY = RGBColor(46, 111, 158)
    COLOR_ACCENT = RGBColor(217, 119, 6)
    COLOR_TEXT = RGBColor(30, 41, 59)
    COLOR_MUTED = RGBColor(100, 116, 139)
    COLOR_WHITE = RGBColor(255, 255, 255)

    # Marges standardisées 1 pouce
    for sec in doc.sections:
        sec.top_margin = Inches(0.9)
        sec.bottom_margin = Inches(0.9)
        sec.left_margin = Inches(0.9)
        sec.right_margin = Inches(0.9)

    # Style Normal
    style_normal = doc.styles['Normal']
    style_normal.font.name = 'Calibri'
    style_normal.font.size = Pt(11)
    style_normal.font.color.rgb = COLOR_TEXT
    style_normal.paragraph_format.line_spacing = 1.15
    style_normal.paragraph_format.space_after = Pt(6)

    # XML Helpers
    def set_cell_background(cell, hex_color):
        shd = parse_xml(f'<w:shd {nsdecls("w")} w:fill="{hex_color}"/>')
        cell._tc.get_or_add_tcPr().append(shd)

    def set_cell_margins(cell, top=140, bottom=140, left=180, right=180):
        tcPr = cell._tc.get_or_add_tcPr()
        tcMar = parse_xml(f'<w:tcMar {nsdecls("w")}><w:top w:w="{top}" w:type="dxa"/><w:bottom w:w="{bottom}" w:type="dxa"/><w:left w:w="{left}" w:type="dxa"/><w:right w:w="{right}" w:type="dxa"/></w:tcMar>')
        tcPr.append(tcMar)

    def set_clean_borders(cell, border_color=HEX_BORDER, top=True, bottom=True, left=True, right=True):
        tcPr = cell._tc.get_or_add_tcPr()
        t = "single" if top else "none"
        b = "single" if bottom else "none"
        l = "single" if left else "none"
        r = "single" if right else "none"
        borders = parse_xml(f'<w:tcBorders {nsdecls("w")}><w:top w:val="{t}" w:sz="4" w:space="0" w:color="{border_color}"/><w:left w:val="{l}" w:sz="4" w:space="0" w:color="{border_color}"/><w:bottom w:val="{b}" w:sz="4" w:space="0" w:color="{border_color}"/><w:right w:val="{r}" w:sz="4" w:space="0" w:color="{border_color}"/></w:tcBorders>')
        tcPr.append(borders)

    # Header de Section en Bannière Pleine Largeur
    def add_section_banner(part_num, part_title, objective):
        tbl = doc.add_table(rows=1, cols=1)
        tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
        tbl.autofit = False
        cell = tbl.cell(0, 0)
        cell.width = Inches(6.7)
        set_cell_background(cell, HEX_PRIMARY)
        set_cell_margins(cell, top=180, bottom=180, left=240, right=200)

        # Bordure dorée à gauche
        tcPr = cell._tc.get_or_add_tcPr()
        borders = parse_xml(f'<w:tcBorders {nsdecls("w")}><w:left w:val="single" w:sz="36" w:space="0" w:color="{HEX_ACCENT}"/><w:top w:val="none"/><w:right w:val="none"/><w:bottom w:val="none"/></w:tcBorders>')
        tcPr.append(borders)

        p = cell.paragraphs[0]
        p.paragraph_format.space_after = Pt(2)
        r_num = p.add_run(f"PARTIE {part_num} — ")
        r_num.bold = True
        r_num.font.size = Pt(13)
        r_num.font.color.rgb = COLOR_ACCENT

        r_title = p.add_run(part_title.upper())
        r_title.bold = True
        r_title.font.size = Pt(13)
        r_title.font.color.rgb = COLOR_WHITE

        p_obj = cell.add_paragraph()
        p_obj.paragraph_format.space_after = Pt(0)
        r_obj_label = p_obj.add_run("Objectif : ")
        r_obj_label.bold = True
        r_obj_label.font.size = Pt(10)
        r_obj_label.font.color.rgb = RGBColor(226, 232, 240)
        
        r_obj_txt = p_obj.add_run(objective)
        r_obj_txt.font.italic = True
        r_obj_txt.font.size = Pt(10)
        r_obj_txt.font.color.rgb = RGBColor(203, 213, 225)

        doc.add_paragraph().paragraph_format.space_after = Pt(6)

    # Titres H2 et H3 stylisés
    def add_h2(text):
        h = doc.add_paragraph()
        h.paragraph_format.space_before = Pt(14)
        h.paragraph_format.space_after = Pt(6)
        h.paragraph_format.keep_with_next = True
        
        # Petit badge carré avant le titre
        r_bullet = h.add_run("■ ")
        r_bullet.font.size = Pt(12)
        r_bullet.font.color.rgb = COLOR_ACCENT
        
        r_txt = h.add_run(text)
        r_txt.bold = True
        r_txt.font.size = Pt(13.5)
        r_txt.font.color.rgb = COLOR_PRIMARY
        return h

    def add_h3(text):
        h = doc.add_paragraph()
        h.paragraph_format.space_before = Pt(10)
        h.paragraph_format.space_after = Pt(4)
        h.paragraph_format.keep_with_next = True
        
        r_txt = h.add_run(text)
        r_txt.bold = True
        r_txt.font.size = Pt(11.5)
        r_txt.font.color.rgb = COLOR_SECONDARY
        return h

    # Encadré Callout Design
    def add_callout(prefix, text, border_color=HEX_PRIMARY, bg_color=HEX_BG_LIGHT):
        tbl = doc.add_table(rows=1, cols=1)
        tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
        tbl.autofit = False
        cell = tbl.cell(0, 0)
        cell.width = Inches(6.7)
        set_cell_background(cell, bg_color)
        set_cell_margins(cell, top=140, bottom=140, left=200, right=160)
        
        tcPr = cell._tc.get_or_add_tcPr()
        borders = parse_xml(f'<w:tcBorders {nsdecls("w")}><w:left w:val="single" w:sz="30" w:space="0" w:color="{border_color}"/><w:top w:val="none"/><w:right w:val="none"/><w:bottom w:val="none"/></w:tcBorders>')
        tcPr.append(borders)

        p = cell.paragraphs[0]
        p.paragraph_format.space_after = Pt(0)
        r_p = p.add_run(f"✦ {prefix} : ")
        r_p.bold = True
        r_p.font.color.rgb = COLOR_PRIMARY
        r_t = p.add_run(text)
        r_t.font.color.rgb = COLOR_TEXT
        doc.add_paragraph().paragraph_format.space_after = Pt(4)

    # Boîte pour capture d'écran / image avec cadre d'élégance
    def add_designer_image(image_path, title, description, width=Inches(6.2)):
        if os.path.exists(image_path):
            tbl = doc.add_table(rows=2, cols=1)
            tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
            tbl.autofit = False
            
            # Cellule 1 : Image
            cell_img = tbl.cell(0, 0)
            cell_img.width = Inches(6.5)
            set_cell_background(cell_img, "FFFFFF")
            set_clean_borders(cell_img, border_color=HEX_BORDER, top=True, bottom=False, left=True, right=True)
            set_cell_margins(cell_img, top=140, bottom=80, left=120, right=120)
            
            p_img = cell_img.paragraphs[0]
            p_img.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p_img.paragraph_format.space_after = Pt(0)
            p_img.add_run().add_picture(image_path, width=width)

            # Cellule 2 : Légende stylisée
            cell_cap = tbl.cell(1, 0)
            cell_cap.width = Inches(6.5)
            set_cell_background(cell_cap, HEX_BG_LIGHT)
            set_clean_borders(cell_cap, border_color=HEX_BORDER, top=False, bottom=True, left=True, right=True)
            set_cell_margins(cell_cap, top=80, bottom=100, left=140, right=140)

            p_cap = cell_cap.paragraphs[0]
            p_cap.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p_cap.paragraph_format.space_after = Pt(0)
            
            r_num = p_cap.add_run(f"ILLUSTRATION — {title.upper()} : ")
            r_num.bold = True
            r_num.font.size = Pt(9.5)
            r_num.font.color.rgb = COLOR_PRIMARY

            r_desc = p_cap.add_run(description)
            r_desc.font.italic = True
            r_desc.font.size = Pt(9.0)
            r_desc.font.color.rgb = COLOR_MUTED

            doc.add_paragraph().paragraph_format.space_after = Pt(8)

    # Tableaux avec En-tête Navy et lignes zebra
    def create_styled_table(headers, data, col_widths=None):
        table = doc.add_table(rows=len(data) + 1, cols=len(headers))
        table.alignment = WD_TABLE_ALIGNMENT.CENTER
        table.autofit = False

        # Header Row
        for col_idx, header_text in enumerate(headers):
            cell = table.cell(0, col_idx)
            set_cell_background(cell, HEX_PRIMARY)
            set_clean_borders(cell, border_color="0F243A")
            set_cell_margins(cell, top=120, bottom=120, left=140, right=140)
            p = cell.paragraphs[0]
            p.alignment = WD_ALIGN_PARAGRAPH.LEFT
            r = p.add_run(header_text)
            r.bold = True
            r.font.size = Pt(10)
            r.font.color.rgb = COLOR_WHITE
            if col_widths and col_idx < len(col_widths):
                cell.width = col_widths[col_idx]

        # Data Rows
        for row_idx, row_data in enumerate(data):
            bg = HEX_BG_LIGHT if row_idx % 2 == 1 else "FFFFFF"
            for col_idx, cell_value in enumerate(row_data):
                cell = table.cell(row_idx + 1, col_idx)
                set_cell_background(cell, bg)
                set_clean_borders(cell, border_color=HEX_BORDER)
                set_cell_margins(cell, top=90, bottom=90, left=140, right=140)
                p = cell.paragraphs[0]
                r = p.add_run(str(cell_value))
                r.font.size = Pt(9.5)
                r.font.color.rgb = COLOR_TEXT
                if col_widths and col_idx < len(col_widths):
                    cell.width = col_widths[col_idx]

        doc.add_paragraph().paragraph_format.space_after = Pt(8)
        return table

    # -------------------------------------------------------------
    # 1. PAGE DE GARDE ULTRA-ÉLÉGANTE (HAUTE COUTURE)
    # -------------------------------------------------------------
    # Bandeau Institutionnel Supérieur
    p_top_banner = doc.add_paragraph()
    p_top_banner.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_top_banner.paragraph_format.space_before = Pt(0)
    p_top_banner.paragraph_format.space_after = Pt(12)
    
    r_country = p_top_banner.add_run("ROYAUME DU MAROC  •  ENSEIGNEMENT SUPÉRIEUR ET RECHERCHE SCIENTIFIQUE\n")
    r_country.font.size = Pt(9)
    r_country.bold = True
    r_country.font.color.rgb = COLOR_MUTED

    r_filiere = p_top_banner.add_run("LICENCE PROFESSIONNELLE 157 — DÉVELOPPEMENT WEB & MOBILE")
    r_filiere.font.size = Pt(10.5)
    r_filiere.bold = True
    r_filiere.font.color.rgb = COLOR_PRIMARY

    # Ligne décorative bicolore
    p_line = doc.add_paragraph()
    p_line.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_line.paragraph_format.space_after = Pt(16)
    r_line = p_line.add_run("━" * 48)
    r_line.font.color.rgb = COLOR_ACCENT
    r_line.bold = True

    # Logo Officiel Aeterna Digital Solutions
    logo_path = r"C:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\public\report_assets\aeterna_logo_badge.png"
    if os.path.exists(logo_path):
        p_logo = doc.add_paragraph()
        p_logo.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_logo.paragraph_format.space_after = Pt(14)
        p_logo.add_run().add_picture(logo_path, width=Inches(4.2))

    # Titre du Projet dans une Carte d'Élégance
    card_table = doc.add_table(rows=1, cols=1)
    card_table.alignment = WD_TABLE_ALIGNMENT.CENTER
    card_table.autofit = False
    card_cell = card_table.cell(0, 0)
    card_cell.width = Inches(6.7)
    set_cell_background(card_cell, HEX_PRIMARY)
    set_cell_margins(card_cell, top=200, bottom=200, left=200, right=200)
    
    # Bordure or autour de la carte de titre
    tcPr = card_cell._tc.get_or_add_tcPr()
    borders = parse_xml(f'<w:tcBorders {nsdecls("w")}><w:left w:val="single" w:sz="36" w:space="0" w:color="{HEX_ACCENT}"/><w:top w:val="none"/><w:right w:val="none"/><w:bottom w:val="none"/></w:tcBorders>')
    tcPr.append(borders)

    p_proj_kind = card_cell.paragraphs[0]
    p_proj_kind.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_proj_kind.paragraph_format.space_after = Pt(4)
    r_kind = p_proj_kind.add_run("RAPPORT DE PROJET DE FIN D'ÉTUDES — SOUTENANCE OFFICIELLE")
    r_kind.font.size = Pt(10.5)
    r_kind.bold = True
    r_kind.font.color.rgb = COLOR_ACCENT

    p_card_title = card_cell.add_paragraph()
    p_card_title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_card_title.paragraph_format.space_after = Pt(6)
    r_main_title = p_card_title.add_run("AETERNA")
    r_main_title.font.size = Pt(28)
    r_main_title.bold = True
    r_main_title.font.color.rgb = COLOR_WHITE

    p_card_sub = card_cell.add_paragraph()
    p_card_sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_card_sub.paragraph_format.space_after = Pt(0)
    r_sub = p_card_sub.add_run("Plateforme SaaS Multi-Tenant pour la Gestion du Réseau des Lauréats,\nl'Insertion Professionnelle et la Gouvernance Académique")
    r_sub.font.size = Pt(11)
    r_sub.font.color.rgb = RGBColor(241, 245, 249)

    doc.add_paragraph().paragraph_format.space_after = Pt(14)

    # Image héroïque en page de garde (Campus marocain / Mosquée Hassan II)
    campus_img = r"C:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\public\images\morocco-hassan-bg.jpg"
    if os.path.exists(campus_img):
        p_hero = doc.add_paragraph()
        p_hero.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p_hero.paragraph_format.space_after = Pt(16)
        p_hero.add_run().add_picture(campus_img, width=Inches(5.8))

    # Fiche d'Identité de la Soutenance (Tableau 2 Colonnes Stylisé)
    info_table = doc.add_table(rows=4, cols=2)
    info_table.alignment = WD_TABLE_ALIGNMENT.CENTER
    info_table.autofit = False

    info_data = [
        ("Candidat / Auteur :", "Fregis OMANDA — Chef de Projet Web & Lead Full-Stack"),
        ("Formation Préparée :", "Licence Professionnelle 157 (Développement d'Applications Web & Mobiles)"),
        ("Agence Fictive Éditrice :", "Aeterna Digital Solutions SARL d'AU (Casablanca, Maroc)"),
        ("Tuteurs & Encadrants :", "Commission Pédagogique d'Évaluation LP 157 — Session 2025/2026")
    ]

    for idx, (label, val) in enumerate(info_data):
        c_label = info_table.cell(idx, 0)
        c_val = info_table.cell(idx, 1)
        c_label.width = Inches(2.2)
        c_val.width = Inches(4.5)

        set_cell_background(c_label, "F1F5F9")
        set_cell_background(c_val, "FFFFFF")
        set_clean_borders(c_label, border_color=HEX_BORDER)
        set_clean_borders(c_val, border_color=HEX_BORDER)
        set_cell_margins(c_label, top=80, bottom=80, left=120, right=120)
        set_cell_margins(c_val, top=80, bottom=80, left=120, right=120)

        p_l = c_label.paragraphs[0]
        r_l = p_l.add_run(label)
        r_l.bold = True
        r_l.font.size = Pt(9.5)
        r_l.font.color.rgb = COLOR_PRIMARY

        p_v = c_val.paragraphs[0]
        r_v = p_v.add_run(val)
        r_v.font.size = Pt(9.5)
        r_v.font.color.rgb = COLOR_TEXT

    # Saut de page pour démarrer le sommaire
    doc.add_page_break()

    # -------------------------------------------------------------
    # 2. SOMMAIRE DÉTAILLÉ AVEC PAGINATION OFFICIELLE
    # -------------------------------------------------------------
    h_somm = doc.add_paragraph()
    h_somm.paragraph_format.space_before = Pt(12)
    h_somm.paragraph_format.space_after = Pt(12)
    r_somm = h_somm.add_run("SOMMAIRE GÉNÉRAL DU RAPPORT")
    r_somm.bold = True
    r_somm.font.size = Pt(18)
    r_somm.font.color.rgb = COLOR_PRIMARY

    add_callout("Structure Officielle", "Ce rapport est rigoureusement conforme au modèle prescrit pour la Licence Professionnelle 157 Développement, articulé en 4 grandes parties, introduction, conclusion et annexes.", border_color=HEX_ACCENT)

    sommaire_items = [
        ("INTRODUCTION GÉNÉRALE", "Contexte, Problématique, Présentation du projet et Annonce du plan", "Page 3"),
        ("PARTIE 1 : L'AGENCE WEB FICTIVE", "1.1 Identité de l'agence (Nom, logo, slogan, mission, valeurs)\n1.2 Portée et positionnement (Zone géographique, services, équipe)", "Page 4"),
        ("PARTIE 2 : LE PROJET — ÉTUDE ET STRATÉGIE", "2.1 Cibles du projet (Personas et synthèse enquête de 150 sondés)\n2.2 Modèle économique (Proposition de valeur, flux de revenus, rentabilité)\n2.3 Marché et environnement (Benchmark concurrentiel)", "Page 6"),
        ("PARTIE 3 : LE PROJET — CONCEPTION ET RÉALISATION", "3.1 Conception graphique (Charte, arborescence, maquettes UI/UX réelles)\n3.2 Réalisation technique (Stack Laravel 11/React/Inertia, multi-tenant, sécurité)", "Page 9"),
        ("PARTIE 4 : LANCEMENT ET GESTION DU PROJET", "4.1 Stratégie de communication (Outils Print, Web et événementiels)\n4.2 Planning et équipe (Gantt sur 6 mois, méthodologie Scrum, matrice RACI)\n4.3 Budget prévisionnel (Fonds de démarrage et compte de résultat sur 3 ans)", "Page 13"),
        ("CONCLUSION & PERSPECTIVES", "Synthèse des réalisations techniques, bilan personnel et vision d'avenir", "Page 16"),
        ("ANNEXES COMPLÉMENTAIRES", "Annexe A : Questionnaire d'enquête d'insertion\nAnnexe B : Schéma relationnel de données (MCD/MLD)\nAnnexe C : Conformité réglementaire CNDP (Loi 09-08) & RGPD", "Page 17")
    ]

    create_styled_table(
        ["Section / Chapitre", "Détail des Éléments Traités", "Pagination"],
        sommaire_items,
        col_widths=[Inches(2.4), Inches(3.3), Inches(1.0)]
    )

    doc.add_page_break()

    # -------------------------------------------------------------
    # 3. INTRODUCTION
    # -------------------------------------------------------------
    add_h2("INTRODUCTION GÉNÉRALE")

    add_h3("1. Contexte et Idée de Départ")
    p_intro1 = doc.add_paragraph()
    p_intro1.add_run(
        "Au Maroc, l'enseignement supérieur connaît une transformation numérique majeure marquée par l'expansion "
        "des effectifs universitaires et l'impératif national d'insertion professionnelle rapide des diplômés. "
        "Pourtant, un constat récurrent s'impose : la gestion des lauréats (alumni) au sein de la majorité des universités "
        "marocaines (Facultés des Sciences, FSJES, ENSA, FST) repose encore sur des outils disparates — fichiers Excel "
        "obsolètes, e-mails non lus ou groupes informels sur les réseaux sociaux. "
        "Ce cloisonnement nuit gravement à l'évaluation de la qualité des formations et prive les étudiants actuels d'un mentorat précieux."
    )
    p_intro2 = doc.add_paragraph()
    p_intro2.add_run(
        "L'idée de départ du projet Aeterna est de combler cette rupture en concevant une plateforme logicielle cloud SaaS souveraine, "
        "spécialement étudiée pour les universités et grandes écoles marocaines, capable de fédérer les promotions, "
        "d'automatiser le suivi de l'insertion professionnelle et de connecter directement les diplômés au monde économique."
    )

    add_h3("2. Présentation du Projet en Quelques Lignes")
    p_intro3 = doc.add_paragraph()
    p_intro3.add_run(
        "Aeterna est une application web full-stack développée sous Laravel 11 et React 18 (via le moteur hybride Inertia.js), "
        "offrant une expérience Single Page Application (SPA) sans latence. "
        "Elle intègre une architecture multi-tenant native qui permet d'héberger plusieurs facultés et campus universitaires "
        "de manière étanche avec un niveau de sécurité et de conformité aux normes de la Commission Nationale de contrôle "
        "de la protection des Données à caractère Personnel (CNDP - Loi 09-08)."
    )

    add_h3("3. Annonce du Plan")
    p_intro4 = doc.add_paragraph()
    p_intro4.add_run(
        "Ce rapport retrace la genèse, l'ingénierie et le déploiement du projet selon le plan officiel de la Licence Professionnelle 157 : "
        "la Partie 1 dévoile l'agence web fictive porteuse de la vision ; la Partie 2 formalise l'étude stratégique, les personas et le modèle d'affaires ; "
        "la Partie 3 détaille la conception graphique et la réalisation technique avec captures d'écran réelles et schémas d'architecture ; "
        "enfin, la Partie 4 expose la feuille de route opérationnelle, le planning Gantt et le budget prévisionnel sur 3 ans."
    )

    doc.add_page_break()

    # -------------------------------------------------------------
    # 4. PARTIE 1 : L'AGENCE WEB FICTIVE
    # -------------------------------------------------------------
    add_section_banner(
        "1",
        "L'Agence Web Fictive",
        "Présenter l'entreprise fictive qui réalise le projet, son identité, sa vision et son organisation."
    )

    add_h2("1.1 Identité de l'Agence")
    add_h3("Choix et Justification du Nom, Logo et Slogan")
    p_id1 = doc.add_paragraph()
    p_id1.add_run(
        "• Nom : Aeterna Digital Solutions. Inspiré du latin æternus (« durable, intemporel »), ce nom incarne la pérennité "
        "du lien fraternel et professionnel unissant un lauréat à son université d'origine tout au long de sa carrière.\n"
        "• Logo : Composé d'un monogramme associant la lettre « A », une porte académique mauresque stylisée et le signe de l'infini. "
        "La charte s'appuie sur le bleu nuit (#16375A) symbole de rigueur institutionnelle et l'or impérial (#D97706) symbolisant l'excellence et la réussite.\n"
        "• Slogan : « Bâtir des ponts numériques durables entre le savoir académique et le monde professionnel »."
    )

    add_designer_image(
        logo_path,
        "Identité Visuelle & Logo d'Agence",
        "Monogramme géométrique et typographie corporate d'Aeterna Digital Solutions (Format Vectoriel Haute Définition)."
    )

    add_h3("Mission et Valeurs de l'Agence")
    create_styled_table(
        ["Valeur Fondamentale", "Signification Opérationnelle chez Aeterna", "Impact Client / Utilisateur"],
        [
            ("Excellence Technique", "Refus du code approximatif, respect strict des standards PSR-12, ESLint et TDD.", "Plateforme robuste sans bugs, temps de réponse < 200 ms."),
            ("Souveraineté des Données", "Hébergement éthique et conformité intégrale avec la CNDP (Loi marocaine 09-08).", "Protection absolue de la vie privée des étudiants."),
            ("Inclusion & Solidarité", "Mise en relation bienveillante des promotions pour l'accès équitable à l'emploi.", "Accélération du premier recrutement des lauréats vulnérables.")
        ],
        col_widths=[Inches(1.8), Inches(2.8), Inches(2.1)]
    )

    add_h2("1.2 Portée et Positionnement")
    add_h3("Zone de Présence Géographique")
    p_port1 = doc.add_paragraph()
    p_port1.add_run(
        "Implantée au cœur du pôle technologique de Casablanca (Technopark / Marina), l'agence déploie son action en trois phases stratégiques :\n"
        "1. Phase Locale & Régionale (Année 1) : Établissements universitaires du Grand Casablanca-Settat (Université Hassan II, Faculté des Sciences Ben M'Sick, Ain Chock, ENSEM, ESTC).\n"
        "2. Phase Nationale (Année 2) : Déploiement auprès des 12 universités publiques marocaines et des écoles privées majeures (Rabat, Marrakech, Fès, Tanger).\n"
        "3. Phase Internationale (Année 3) : Expansion vers l'Afrique subsaharienne francophone (Sénégal, Côte d'Ivoire, Cameroun, Gabon)."
    )

    add_h3("Offre de Services et Équipe Projet")
    p_port2 = doc.add_paragraph()
    p_port2.add_run(
        "Aeterna Digital Solutions propose une gamme de services à forte valeur technologique :\n"
        "• Édition SaaS : Mise à disposition, hébergement managé et maintenance de la plateforme Aeterna.\n"
        "• Intégration sur Mesure : Synchronisation avec les systèmes d'information académiques (Apogée, LDAP/SSO universitaire).\n"
        "• Consulting & UX Audit : Accompagnement à la digitalisation des associations de diplômés et respect de la conformité CNDP."
    )

    add_callout(
        "Rôle du Chef de Projet",
        "Fregis Omanda assume la fonction de Chef de Projet & Lead Developer Full-Stack : conception de l'architecture logicielle, pilotage des développements, tests d'intégration et garant de la direction artistique.",
        border_color=HEX_PRIMARY
    )

    doc.add_page_break()

    # -------------------------------------------------------------
    # 5. PARTIE 2 : LE PROJET — ÉTUDE ET STRATÉGIE
    # -------------------------------------------------------------
    add_section_banner(
        "2",
        "Le Projet : Étude et Stratégie",
        "Définir le projet réalisé par l'agence, valider les personas cibles et démontrer sa viabilité économique."
    )

    add_h2("2.1 Cibles du Projet")
    add_h3("Portraits des Utilisateurs Idéaux (Personas Cibles)")

    personas_data = [
        ("Persona 1 : Pr. Amina\n(Responsable Pédagogique)", "52 ans, Directrice des Relations Extérieures", "Obtenir des métriques fiables sur le devenir des promotions pour les rapports d'accréditation du Ministère.", "Taux de réponse de 8% par e-mail traditionnel ; manque de temps pour les relances."),
        ("Persona 2 : Youssef\n(Alumni / Lauréat en Poste)", "27 ans, Ingénieur d'Études chez Devoteam", "Partager des offres de stage de son entreprise et recruter des juniors de confiance de son ancienne filière.", "Perte totale du contact avec les enseignants après la soutenance ; sentiment d'abandon."),
        ("Persona 3 : Salma\n(Étudiante en LP 157)", "22 ans, Étudiante en quête de Stage PFE", "Consulter des offres vérifiées et solliciter un ancien étudiant pour relire son CV technique.", "Candidatures spontanées sur LinkedIn sans retour ; absence de parrainage.")
    ]

    create_styled_table(
        ["Profil & Rôle", "Données Démographiques", "Besoins & Objectifs Primaires", "Frustrations Actuelles"],
        personas_data,
        col_widths=[Inches(1.8), Inches(1.5), Inches(1.9), Inches(1.5)]
    )

    add_h3("Synthèse de l'Enquête Terrain Réalisée")
    p_enq = doc.add_paragraph()
    p_enq.add_run(
        "Une enquête quantitative a été menée auprès d'un échantillon représentatif de 150 étudiants et lauréats de la région de Casablanca. "
        "Les résultats confirment l'opportunité stratégique du projet Aeterna :\n"
        "• 92% des étudiants estiment que l'accès à un réseau d'anciens diplômés est déterminant pour décrocher leur premier emploi.\n"
        "• 87% des diplômés en activité sont disposés à encadrer ou orienter un étudiant si la plateforme est intuitive et sécurisée.\n"
        "• 79% des administrateurs considèrent que les outils actuels (fichiers bureautiques) sont inefficaces et non conformes à la loi 09-08."
    )

    # Infographie Insertion
    insertion_img = r"C:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\public\report_assets\infographie_insertion.png"
    add_designer_image(
        insertion_img,
        "Cycle Vertueux d'Insertion & Mentorat",
        "Workflow en 5 étapes clés depuis la diplomation jusqu'au recrutement et au retour d'expérience institutionnel."
    )

    add_h2("2.2 Modèle Économique du Projet")
    add_h3("Proposition de Valeur et Flux de Revenus")
    p_eco = doc.add_paragraph()
    p_eco.add_run(
        "Aeterna fonctionne selon un modèle d'affaires B2B SaaS Hybride garantissant des revenus prévisibles et récurrents :\n"
        "1. Abonnements Annuels Établissements (Licences Annuelles) :\n"
        "   - Formule Campus Essentiel (jusqu'à 2 500 alumni) : 18 000 MAD HT / an.\n"
        "   - Formule Université Intégrale (Multi-campus, utilisateurs illimités, API ENT) : 42 000 MAD HT / an.\n"
        "2. Monétisation Recrutement & Partenariats Entreprises :\n"
        "   - Forfait 'Pack Chasseur de Talents' : 4 500 MAD pour la publication de 5 offres ciblées et accès au vivier certifié.\n"
        "3. Prestations d'Intégration et Formation : Migration de bases historiques et ateliers utilisateurs (8 000 MAD / établissement)."
    )

    add_h2("2.3 Marché et Environnement Concurrentiel")
    add_h3("Benchmark Comparatif des Solutions")

    benchmark_data = [
        ("Critères d'Évaluation", "Réseau Généraliste (LinkedIn)", "Solutions Étrangères (Hivebrite)", "Aeterna SaaS (Notre Solution)"),
        ("Souveraineté & CNDP", "Non (Serveurs internationaux)", "Partielle (RGPD UE)", "Totale (Conformité stricte Loi 09-08)"),
        ("Coût Annuel", "Gratuit / Abonnements chers", "Élevé (> 120 000 MAD / an)", "Accessible & Compétitif (18k à 42k MAD)"),
        ("Statistiques Administrateur", "Très limitées / Floues", "Complexes et en anglais", "Tableaux de bord KPI clé en main"),
        ("Support & Langues", "Automatisé / Anglais", "Support distant décalé", "Support local bilingue (Français/Arabe)"),
        ("Isolation Multi-Tenant", "Aucune (Réseau public)", "Complexe à paramétrer", "Native et automatique dès l'inscription")
    ]

    create_styled_table(
        benchmark_data[0],
        benchmark_data[1:],
        col_widths=[Inches(1.8), Inches(1.6), Inches(1.6), Inches(1.7)]
    )

    # Graphique Benchmark
    benchmark_img = r"C:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\public\report_assets\benchmark_radar.png"
    add_designer_image(
        benchmark_img,
        "Analyse Comparative des Solutions du Marché",
        "Évaluation sur 5 axes stratégiques : Souveraineté CNDP, Coût, KPIs, Ergonomie UX et Accompagnement local."
    )

    doc.add_page_break()

    # -------------------------------------------------------------
    # 6. PARTIE 3 : LE PROJET — CONCEPTION ET RÉALISATION
    # -------------------------------------------------------------
    add_section_banner(
        "3",
        "Le Projet : Conception et Réalisation",
        "Démontrer la rigueur de la conception graphique UX/UI et la qualité de la mise en œuvre technique full-stack."
    )

    add_h2("3.1 Conception Graphique")
    add_h3("Charte Graphique et Recherche Ergonomique")
    p_cg1 = doc.add_paragraph()
    p_cg1.add_run(
        "La conception de l'interface répond aux exigences de la démarche Impeccable Design : hiérarchie visuelle stricte, "
        "contraste WCAG AA validé, suppression des surcharges cognitives et ancrage culturel valorisant le patrimoine académique marocain. "
        "La palette chromatique associe :\n"
        "• Bleu Nuit Profond (#16375A) : Structure, sidebar, en-têtes et cartes de haut niveau.\n"
        "• Or Impérial & Ambre (#D97706) : Éléments d'action (CTA), badges d'honneur, indicateurs d'excellence.\n"
        "• Ardoise & Fond Neutre (#F8FAFC / #0F172A en mode sombre) : Confort de lecture prolongé.\n"
        "• Typographies : Inter pour la finesse et lisibilité des données tabulaires ; Outfit pour les titres d'impact."
    )

    # Capture 1 : Page de connexion avec décor Mosquée Hassan II
    login_img = r"C:\Users\Fregis Omanda\.gemini\antigravity-ide\brain\6d0787f5-9553-4973-b181-531979e84665\hassan_mosque_login_bg_1791920600960.png"
    add_designer_image(
        login_img,
        "Écran de Connexion Multi-Tenant — Aeterna Auth",
        "Interface d'authentification sécurisée avec arrière-plan patrimonial (Mosquée Hassan II & Campus Universitaire de Casablanca)."
    )

    add_h3("Arborescence et Parcours Utilisateur")
    p_arbo = doc.add_paragraph()
    p_arbo.add_run(
        "L'arborescence est organisée en zones fonctionnelles claires :\n"
        "1. Zone Publique : Landing page institutionnelle, présentation des campus affiliés, portail de connexion et inscription.\n"
        "2. Espace Étudiant & Lauréat : Tableau de bord personnel, annuaire dynamique des alumni avec filtres avancés, offres de stages/emplois, messagerie et demande de mentorat.\n"
        "3. Espace Administration (Back-Office) : Gestion des utilisateurs, validation des diplômes, statistiques d'insertion en temps réel, corbeille et archivage RGPD/CNDP."
    )

    # Capture 2 : Dashboard
    dash_img = r"C:\Users\Fregis Omanda\.gemini\antigravity-ide\brain\6d0787f5-9553-4973-b181-531979e84665\dashboard_final_check_1791918312670.png"
    add_designer_image(
        dash_img,
        "Tableau de Bord Analytique — KPIs en Temps Réel",
        "Vue d'ensemble avec indicateurs de placement, répartition par promotion et raccourcis d'administration."
    )

    # Capture 3 : Annuaire des lauréats
    alumni_img = r"C:\Users\Fregis Omanda\.gemini\antigravity-ide\brain\6d0787f5-9553-4973-b181-531979e84665\alumni_page_audit_1791919881678.png"
    add_designer_image(
        alumni_img,
        "Annuaire Dynamique des Lauréats & Filtres",
        "Moteur de recherche multicritères (filière, année de diplomation, entreprise, poste) et cartes de profil enrichies."
    )

    add_h2("3.2 Réalisation Technique")
    add_h3("Choix Techniques et Justification de la Stack")
    p_tech = doc.add_paragraph()
    p_tech.add_run(
        "Le projet adopte une architecture moderne alliant la robustesse d'un backend MVC d'entreprise et la fluidité d'un frontend réactif :\n"
        "• Backend : PHP 8.2+ avec Laravel 11. Ce choix garantit un ORM puissant (Eloquent), un système de migration versionné, "
        "la gestion sécurisée de l'authentification (Laravel Fortify / Breeze) et un écosystème de middleware adapté au multi-tenant.\n"
        "• Frontend : React 18 orchestré par Inertia.js. Ce couple technologique élimine la complexité et les temps de latence "
        "d'une API REST découplée tout en offrant le confort d'une véritable Single Page Application (navigation instantanée sans rechargement de page).\n"
        "• Styling : Tailwind CSS v3 avec un design system sur mesure et des utilitaires de contraste validés.\n"
        "• Persistance : Base de données relationnelle MySQL avec indexation fine sur les clés étrangères et gestion des partitions par établissement."
    )

    # Schéma d'architecture technique
    arch_img = r"C:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\public\report_assets\architecture_schema.png"
    add_designer_image(
        arch_img,
        "Architecture Système & Étanchéité Multi-Tenant",
        "Diagramme des couches applicatives, middleware de résolution d'établissement, moteur Laravel 11 et conformité CNDP."
    )

    add_h3("Back-Office, Sécurité et Conventions de Développement")
    p_back = doc.add_paragraph()
    p_back.add_run(
        "• Contrôle d'Accès Basé sur les Rôles (RBAC) : Politiques d'autorisation Laravel (Policies) strictes séparant les privilèges SuperAdmin, Admin Faculté, Alumni et Étudiant.\n"
        "• Isolation Multi-Tenant : Chaque requête est interceptée par un middleware qui résout l'université active et applique automatiquement un Global Scope sur toutes les requêtes Eloquent, interdisant toute fuite de données inter-campus.\n"
        "• Conventions de Nommage : Respect absolu des normes PSR-12 pour le code PHP, camelCase pour les composants React et noms de routes RESTful explicites.\n"
        "• Sécurité et CNDP : Hachage Bcrypt des mots de passe, protection contre les injections SQL via requêtes préparées PDO, tokens CSRF systématiques et purge conforme au droit à l'oubli."
    )

    # Capture 4 : Gestion des utilisateurs
    users_img = r"C:\Users\Fregis Omanda\.gemini\antigravity-ide\brain\6d0787f5-9553-4973-b181-531979e84665\users_page_audit_1791919786658.png"
    add_designer_image(
        users_img,
        "Gestion des Utilisateurs & Attribution des Rôles",
        "Tableau d'administration des comptes avec filtres de statut, actions rapides, initiales dynamiques et avatars certifiés."
    )

    # Schéma Relationnel BD (MLD)
    mld_img = r"C:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\public\report_assets\schema_mcd_database.png"
    add_designer_image(
        mld_img,
        "Modèle Logique de Données (MLD) Multi-Tenant",
        "Structure relationnelle des entités : universités, utilisateurs, profils alumni, offres d'emploi et parrainages."
    )

    doc.add_page_break()

    # -------------------------------------------------------------
    # 7. PARTIE 4 : LANCEMENT ET GESTION DU PROJET
    # -------------------------------------------------------------
    add_section_banner(
        "4",
        "Lancement et Gestion du Projet",
        "Expliquer la stratégie marketing de déploiement, le calendrier prévisionnel et l'ingénierie financière."
    )

    add_h2("4.1 Stratégie de Communication")
    add_h3("Dispositif Multicanal Print, Web et Événementiel")
    
    comm_data = [
        ("Canal & Outils", "Description de l'Action", "Public Visé", "Indicateur Clé (KPI)"),
        ("Print Prestige", "Plaquette commerciale quadri 4 pages + Chemise cartonnée officielle avec QR Code.", "Doyens, Présidents d'universités, Directeurs d'écoles", "Taux de rendez-vous obtenus (> 35%)"),
        ("Campagnes Web", "Sponsoring LinkedIn ciblé sur les décideurs RH et directeurs de filières marocaines.", "Responsables pédagogiques, Responsables recrutement", "Coût par lead qualifié < 120 MAD"),
        ("Démonstration Live", "Capsules vidéo interactives et espace de démonstration sandbox en libre accès.", "Enseignants, présidents de BDE, diplômés", "Nombre de comptes de test créés (> 200)"),
        ("Événementiel", "Organisation du Webinaire National : « Digitaliser le suivi des lauréats selon la loi 09-08 ».", "Écosystème académique marocain complet", "Plus de 180 participants connectés")
    ]

    create_styled_table(
        comm_data[0],
        comm_data[1:],
        col_widths=[Inches(1.5), Inches(2.3), Inches(1.7), Inches(1.2)]
    )

    add_h2("4.2 Planning et Organisation de l'Équipe")
    add_h3("Méthodologie Scrum et Diagramme de Gantt")
    p_plan = doc.add_paragraph()
    p_plan.add_run(
        "Le projet a été piloté selon la méthodologie Agile Scrum avec des sprints de deux semaines, rythmés par des cérémonies "
        "de Sprint Planning, Daily Stand-ups et Revues de Sprint. Le calendrier s'articule sur une période totale de 6 mois :"
    )

    # Graphique Gantt
    gantt_img = r"C:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\public\report_assets\gantt_chart.png"
    add_designer_image(
        gantt_img,
        "Diagramme de Gantt — Déroulement Chronologique",
        "Planning de développement par phases successives depuis le cadrage initial jusqu'à la soutenance et la mise en production."
    )

    add_h3("Matrice des Rôles et Responsabilités (RACI)")
    raci_data = [
        ("Tâche Majeure du Projet", "Fregis Omanda (Chef de Projet)", "Comité Pédagogique LP 157", "Direction Informatique Campus"),
        ("Cadrage Fonctionnel & Spécifications", "R (Réalisateur)", "A (Approbateur)", "C (Consulté)"),
        ("Conception Graphique UI/UX", "R (Réalisateur)", "C (Consulté)", "I (Informé)"),
        ("Développement Backend Laravel & Multi-Tenant", "R (Réalisateur)", "I (Informé)", "C (Consulté)"),
        ("Intégration Frontend React & Inertia", "R (Réalisateur)", "I (Informé)", "I (Informé)"),
        ("Validation Sécurité & Déclaration CNDP", "R (Réalisateur)", "A (Approbateur)", "C (Consulté)")
    ]

    create_styled_table(
        raci_data[0],
        raci_data[1:],
        col_widths=[Inches(2.4), Inches(1.5), Inches(1.4), Inches(1.4)]
    )

    add_h2("4.3 Budget Prévisionnel et Rentabilité")
    add_h3("Fonds Nécessaires au Lancement (Capital Initial)")
    p_bud = doc.add_paragraph()
    p_bud.add_run(
        "Le budget initial requis pour amorcer l'activité d'Aeterna Digital Solutions s'élève à 65 000 MAD, ventilé comme suit :\n"
        "• Frais juridiques et constitution de la SARL AU (RC, Patente, Notaire) : 6 500 MAD.\n"
        "• Infrastructure Cloud & Sécurité (Serveur VPS dédié, certificats SSL, nom de domaine) : 12 000 MAD / an.\n"
        "• Licences logicielles et outils de prototypage : 8 500 MAD.\n"
        "• Budget de communication, impression print et promotion digitale de lancement : 23 000 MAD.\n"
        "• Fonds de roulement de sécurité : 15 000 MAD."
    )

    add_h3("Compte de Résultat Prévisionnel sur 3 Ans")
    fin_chart_img = r"C:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\public\report_assets\financial_chart.png"
    add_designer_image(
        fin_chart_img,
        "Modèle Financier & Seuil de Rentabilité à 3 Ans",
        "Projections d'évolution du chiffre d'affaires, des charges d'exploitation et du résultat net bénéficiaire."
    )

    fin_table_data = [
        ("Indicateurs Financiers", "Année 1 (Amorçage)", "Année 2 (Déploiement National)", "Année 3 (Expansion Maghreb)"),
        ("Nombre de Campus Clients", "4 établissements", "12 établissements", "28 établissements"),
        ("Vente de Packs Recrutement", "10 packs", "35 packs", "85 packs"),
        ("Chiffre d'Affaires Total", "117 000 MAD", "328 000 MAD", "760 000 MAD"),
        ("Charges d'Exploitation", "85 000 MAD", "183 000 MAD", "380 000 MAD"),
        ("Résultat Net Bénéficiaire", "+32 000 MAD", "+145 000 MAD", "+380 000 MAD"),
        ("Marge Nette", "27,3 %", "44,2 %", "50,0 %")
    ]

    create_styled_table(
        fin_table_data[0],
        fin_table_data[1:],
        col_widths=[Inches(2.2), Inches(1.5), Inches(1.5), Inches(1.5)]
    )

    add_callout(
        "Seuil de Rentabilité",
        "Le point mort (Break-even point) est atteint dès le 7ème mois d'activité avec seulement 3 universités clientes souscrivant à la formule Campus Essentiel.",
        border_color=HEX_ACCENT
    )

    doc.add_page_break()

    # -------------------------------------------------------------
    # 8. CONCLUSION ET ANNEXES
    # -------------------------------------------------------------
    add_h2("CONCLUSION GÉNÉRALE")
    p_c1 = doc.add_paragraph()
    p_c1.add_run(
        "Ce projet de fin d'études a permis de concevoir et de réaliser une solution logicielle SaaS complète, "
        "démontrant la faisabilité technique et la viabilité commerciale de la plateforme Aeterna. "
        "Sur le plan technique, l'intégration de Laravel 11 avec React 18 via Inertia.js et la mise en œuvre d'une architecture multi-tenant "
        "prouvent qu'il est possible d'offrir une expérience utilisateur fluide tout en garantissant une étanchéité absolue des données."
    )
    p_c2 = doc.add_paragraph()
    p_c2.add_run(
        "Sur le plan managérial et sociétal, Aeterna apporte une réponse concrète aux défis d'insertion des diplômés au Maroc "
        "en valorisant le capital humain universitaire et en renforçant les liens intergénérationnels. "
        "Les perspectives d'évolution portent sur l'intégration d'un moteur d'intelligence artificielle pour l'appariement automatique "
        "des offres d'emploi selon les compétences acquises par les lauréats."
    )

    add_h2("ANNEXES COMPLÉMENTAIRES")

    add_h3("Annexe A : Grille des Questions de l'Enquête d'Insertion")
    p_ann1 = doc.add_paragraph()
    p_ann1.add_run(
        "1. Dans quelle filière et université avez-vous obtenu votre dernier diplôme ?\n"
        "2. Combien de mois se sont écoulés entre l'obtention de votre diplôme et votre premier contrat de travail ?\n"
        "3. Le réseau des anciens étudiants de votre école vous a-t-il aidé dans vos recherches ? (Échelle 1 à 5)\n"
        "4. Seriez-vous prêt à devenir parrain ou mentor pour un étudiant de votre ancienne filière ? (Oui / Non / Peut-être)\n"
        "5. Quelles fonctionnalités jugeriez-vous indispensables sur un portail universitaire dédié ?"
    )

    add_h3("Annexe B : Schéma Relationnel de Données (MCD/MLD)")
    p_ann2 = doc.add_paragraph()
    p_ann2.add_run(
        "• Table universities (id, name, slug, domain, logo_url, is_active, created_at)\n"
        "• Table users (id, university_id, first_name, last_name, email, role, avatar_path, phone, is_verified)\n"
        "• Table alumni_profiles (id, user_id, graduation_year, current_company, job_title, linkedin_url, bio)\n"
        "• Table job_offers (id, university_id, author_id, title, company, location, contract_type, description, status)\n"
        "• Table mentor_requests (id, student_id, alumni_id, message, status, scheduled_at)"
    )

    add_h3("Annexe C : Déclaration et Conformité Réglementaire CNDP (Loi 09-08)")
    p_ann3 = doc.add_paragraph()
    p_ann3.add_run(
        "La plateforme intègre dès sa conception les exigences de la Commission Nationale de contrôle de la protection des Données à caractère Personnel :\n"
        "• Consentement explicite lors de la première inscription avec acceptation des conditions d'utilisation.\n"
        "• Module d'exercice du droit à l'oubli et export de l'intégralité des données personnelles au format JSON.\n"
        "• Registre des traitements internes et journalisation d'audit des actions administratives sensibles."
    )

    # Infographie Sécurité CNDP
    sec_img = r"C:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\public\report_assets\security_cndp_schema.png"
    add_designer_image(
        sec_img,
        "Gouvernance de Sécurité & Conformité CNDP",
        "Piliers de protection : chiffrement Bcrypt/AES, étanchéité multi-tenant, droit à l'oubli et traçabilité d'audit."
    )

    # -------------------------------------------------------------
    # 9. EN-TÊTES ET PIEDS DE PAGE SUR LES PAGES DE CONTENU
    # -------------------------------------------------------------
    for i, section in enumerate(doc.sections):
        # Header
        header = section.header
        header.is_linked_to_previous = False
        p_hdr = header.paragraphs[0]
        p_hdr.alignment = WD_ALIGN_PARAGRAPH.RIGHT
        p_hdr.text = "AETERNA DIGITAL SOLUTIONS — RAPPORT DE SOUTENANCE LP 157"
        p_hdr.runs[0].font.name = 'Calibri'
        p_hdr.runs[0].font.size = Pt(8.5)
        p_hdr.runs[0].font.color.rgb = COLOR_MUTED

        # Footer
        footer = section.footer
        footer.is_linked_to_previous = False
        p_ftr = footer.paragraphs[0]
        p_ftr.alignment = WD_ALIGN_PARAGRAPH.LEFT
        p_ftr.text = "Licence Professionnelle 157 Développement  |  Université Hassan II de Casablanca"
        p_ftr.runs[0].font.name = 'Calibri'
        p_ftr.runs[0].font.size = Pt(8.5)
        p_ftr.runs[0].font.color.rgb = COLOR_MUTED

    # Sauvegarde des documents Word
    out_path_designer = r"c:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\Rapport_Projet_Web_Aeterna_LP157_Design.docx"
    out_path_1 = r"c:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\Rapport_Projet_Web_Aeterna_LP157.docx"
    out_path_2 = r"c:\Users\Fregis Omanda\Downloads\Aeterna-main\Rapport_Projet_Web_Aeterna_LP157.docx"

    # Toujours sauvegarder la version Design dédiée
    doc.save(out_path_designer)
    print(f"Rapport Design généré avec succès dans : {out_path_designer}")

    # Tenter de mettre à jour le fichier principal si non verrouillé par Word
    try:
        doc.save(out_path_1)
        print(f"Mis à jour également : {out_path_1}")
    except Exception as e:
        print(f"Note : {out_path_1} est actuellement ouvert dans Word : {e}")

    try:
        doc.save(out_path_2)
        print(f"Mis à jour également : {out_path_2}")
    except Exception as e:
        print(f"Note : {out_path_2} est actuellement ouvert dans Word : {e}")

    # -------------------------------------------------------------
    # 10. EXPORT OFFICIEL VERS LE PDF HAUTE DÉFINITION VIA WORD COM
    # -------------------------------------------------------------
    pdf_path_1 = r"c:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\Rapport_Projet_Web_Aeterna_LP157.pdf"
    pdf_path_2 = r"c:\Users\Fregis Omanda\Downloads\Aeterna-main\Rapport_Projet_Web_Aeterna_LP157.pdf"

    try:
        import win32com.client
        word = win32com.client.Dispatch("Word.Application")
        word.Visible = False
        try:
            in_doc = word.Documents.Open(os.path.abspath(out_path_designer))
            in_doc.SaveAs(os.path.abspath(pdf_path_1), FileFormat=17) # 17 = wdFormatPDF
            in_doc.Close()
            print(f"PDF Officiel généré avec succès dans : {pdf_path_1}")
            
            import shutil
            shutil.copyfile(pdf_path_1, pdf_path_2)
            print(f"PDF copié également dans : {pdf_path_2}")
        finally:
            word.Quit()
    except Exception as err:
        print(f"Export PDF via Word COM : {err}")

if __name__ == "__main__":
    build_designer_report()
