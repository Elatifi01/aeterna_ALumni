import os
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.patches as patches
import numpy as np
from PIL import Image, ImageDraw, ImageFont

output_dir = r"C:\Users\Fregis Omanda\Downloads\Aeterna-main\Aeterna-main\public\report_assets"
os.makedirs(output_dir, exist_ok=True)

# -------------------------------------------------------------
# 1. INFOGRAPHIE : CYCLE D'INSERTION & MENTORAT ALUMNI
# -------------------------------------------------------------
def generate_insertion_infographic():
    fig, ax = plt.subplots(figsize=(12, 4.5), dpi=200)
    ax.set_facecolor('#F8FAFC')
    fig.patch.set_facecolor('#FFFFFF')

    steps = [
        ("1. Cursus & Remise", "Étudiant en cours\nde formation LP 157", "#3B82F6"),
        ("2. Profil Certifié", "Création compte alumni\net CV académique", "#6366F1"),
        ("3. Réseau & Parrainage", "Matching automatique\navec un mentor actif", "#8B5CF6"),
        ("4. Offres & Entretiens", "Candidature aux offres\nexclusives vérifiées", "#D97706"),
        ("5. Premier Emploi", "Recrutement réussi &\nKPI transmises à l'école", "#10B981")
    ]

    for i, (title, desc, color) in enumerate(steps):
        x = 0.5 + i * 2.3
        y = 1.0
        # Card box
        rect = patches.FancyBboxPatch((x, y), 2.0, 2.2, boxstyle="round,pad=0.04,rounding_size=0.15",
                                      facecolor='#FFFFFF', edgecolor=color, linewidth=2.5, zorder=2)
        ax.add_patch(rect)
        
        # Circle badge
        circle = plt.Circle((x + 1.0, y + 1.8), 0.32, color=color, zorder=3)
        ax.add_patch(circle)
        ax.text(x + 1.0, y + 1.8, str(i + 1), ha='center', va='center', fontsize=12, fontweight='bold', color='#FFFFFF', zorder=4)

        # Texts
        ax.text(x + 1.0, y + 1.15, title, ha='center', va='center', fontsize=10.5, fontweight='bold', color='#16375A', zorder=3)
        ax.text(x + 1.0, y + 0.55, desc, ha='center', va='center', fontsize=8.5, color='#475569', zorder=3)

        # Arrow to next step
        if i < len(steps) - 1:
            ax.annotate('', xy=(x + 2.3, y + 1.1), xytext=(x + 2.0, y + 1.1),
                        arrowprops=dict(facecolor='#16375A', edgecolor='none', width=2.5, headwidth=7), zorder=1)

    ax.set_xlim(0, 12)
    ax.set_ylim(0.4, 3.8)
    ax.axis('off')
    plt.title("Cycle Vertueux d'Insertion Professionnelle & de Mentorat — Aeterna Platform",
              fontsize=13, fontweight='bold', color='#16375A', pad=15)

    path = os.path.join(output_dir, "infographie_insertion.png")
    plt.tight_layout()
    plt.savefig(path, bbox_inches='tight', dpi=200)
    plt.close()
    print("Infographie insertion générée:", path)

# -------------------------------------------------------------
# 2. BENCHMARK COMPARATIF GRAPHIQUE (Radar / Barres)
# -------------------------------------------------------------
def generate_benchmark_chart():
    fig, ax = plt.subplots(figsize=(10, 5.2), dpi=200)
    ax.set_facecolor('#F8FAFC')
    fig.patch.set_facecolor('#FFFFFF')

    criteria = [
        "Souveraineté\n& Loi CNDP",
        "Rapport Qualité\n/ Prix Annuel",
        "Statistiques KPI\n& Débouchés",
        "Ergonomie\n& Modernité UX",
        "Accompagnement\nLocal Maroc"
    ]
    
    linkedin_scores = [3.0, 5.5, 4.0, 8.5, 3.5]
    hivebrite_scores = [6.5, 3.5, 7.0, 7.5, 4.0]
    aeterna_scores = [10.0, 9.5, 9.5, 9.8, 10.0]

    x = np.arange(len(criteria))
    width = 0.26

    ax.bar(x - width, linkedin_scores, width, label='LinkedIn Généraliste', color='#94A3B8', edgecolor='#64748B', zorder=3)
    ax.bar(x, hivebrite_scores, width, label='Solutions Européennes (Hivebrite)', color='#3B82F6', edgecolor='#1D4ED8', zorder=3)
    ax.bar(x + width, aeterna_scores, width, label='Aeterna SaaS (Notre Solution)', color='#D97706', edgecolor='#B45309', zorder=3)

    ax.set_ylabel('Score d\'Adéquation / 10', fontsize=11, fontweight='bold', color='#1E293B')
    ax.set_title('Benchmark Comparatif — Évaluation de la Valeur Ajoutée d\'Aeterna', fontsize=13, fontweight='bold', color='#16375A', pad=15)
    ax.set_xticks(x)
    ax.set_xticklabels(criteria, fontsize=9.5, fontweight='bold', color='#334155')
    ax.set_ylim(0, 11.5)
    ax.legend(frameon=True, facecolor='#FFFFFF', edgecolor='#CBD5E1', fontsize=9.5)
    ax.grid(axis='y', linestyle='--', alpha=0.5, zorder=0)

    path = os.path.join(output_dir, "benchmark_radar.png")
    plt.tight_layout()
    plt.savefig(path, bbox_inches='tight', dpi=200)
    plt.close()
    print("Benchmark chart généré:", path)

# -------------------------------------------------------------
# 3. SCHÉMA RELATIONNEL DE DONNÉES (MCD / MLD)
# -------------------------------------------------------------
def generate_database_schema():
    fig, ax = plt.subplots(figsize=(11, 6.0), dpi=200)
    ax.set_facecolor('#F8FAFC')
    fig.patch.set_facecolor('#FFFFFF')

    def draw_entity(x, y, w, h, table_name, fields, color='#16375A'):
        # Outer box
        rect = patches.FancyBboxPatch((x, y), w, h, boxstyle="round,pad=0.03,rounding_size=0.1",
                                      facecolor='#FFFFFF', edgecolor=color, linewidth=2, zorder=2)
        ax.add_patch(rect)
        # Header banner
        header = patches.FancyBboxPatch((x, y + h - 0.45), w, 0.45, boxstyle="round,pad=0.03,rounding_size=0.1",
                                        facecolor=color, edgecolor=color, linewidth=2, zorder=3)
        ax.add_patch(header)
        ax.text(x + w/2, y + h - 0.25, table_name, ha='center', va='center', fontsize=10.5, fontweight='bold', color='#FFFFFF', zorder=4)
        
        # Fields
        for idx, fld in enumerate(fields):
            ax.text(x + 0.15, y + h - 0.7 - idx * 0.28, fld, fontsize=8.5, color='#334155', zorder=3)

    # Tables
    draw_entity(0.5, 3.2, 3.0, 2.4, "universities", [
        "🔑 id: BIGINT (PK)",
        "• name: VARCHAR",
        "• slug: VARCHAR (Index)",
        "• domain: VARCHAR",
        "• is_active: BOOLEAN",
        "• created_at: TIMESTAMP"
    ], color='#16375A')

    draw_entity(4.5, 3.0, 3.2, 2.6, "users", [
        "🔑 id: BIGINT (PK)",
        "🔗 university_id: BIGINT (FK)",
        "• first_name, last_name",
        "• email: VARCHAR (Unique)",
        "• role: ENUM(admin, alumni...)",
        "• avatar_path: VARCHAR",
        "• password: HASH (Bcrypt)"
    ], color='#2E6F9E')

    draw_entity(8.5, 3.2, 3.0, 2.4, "alumni_profiles", [
        "🔑 id: BIGINT (PK)",
        "🔗 user_id: BIGINT (FK)",
        "• graduation_year: INT",
        "• degree: VARCHAR",
        "• current_company: VARCHAR",
        "• job_title: VARCHAR",
        "• city: VARCHAR"
    ], color='#D97706')

    draw_entity(1.5, 0.4, 3.8, 2.1, "job_offers", [
        "🔑 id: BIGINT (PK)",
        "🔗 university_id, author_id (FK)",
        "• title, company, location",
        "• contract_type: ENUM",
        "• status: ENUM(active, closed)"
    ], color='#10B981')

    draw_entity(6.5, 0.4, 3.8, 2.1, "mentor_requests", [
        "🔑 id: BIGINT (PK)",
        "🔗 student_id, alumni_id (FK)",
        "• message: TEXT",
        "• status: ENUM(pending, accepted)",
        "• scheduled_at: TIMESTAMP"
    ], color='#8B5CF6')

    # Relations lines
    ax.annotate('', xy=(4.5, 4.4), xytext=(3.5, 4.4),
                arrowprops=dict(facecolor='#16375A', edgecolor='none', width=2, headwidth=6))
    ax.annotate('', xy=(8.5, 4.4), xytext=(7.7, 4.4),
                arrowprops=dict(facecolor='#D97706', edgecolor='none', width=2, headwidth=6))
    ax.annotate('', xy=(3.5, 2.5), xytext=(4.5, 3.2),
                arrowprops=dict(facecolor='#10B981', edgecolor='none', width=2, headwidth=6))
    ax.annotate('', xy=(8.0, 2.5), xytext=(6.5, 3.0),
                arrowprops=dict(facecolor='#8B5CF6', edgecolor='none', width=2, headwidth=6))

    ax.set_xlim(0, 12)
    ax.set_ylim(0.2, 6.0)
    ax.axis('off')
    plt.title("Modèle Logique de Données (MLD) Relationnel — Multi-Tenant Data Schema",
              fontsize=13, fontweight='bold', color='#16375A', pad=15)

    path = os.path.join(output_dir, "schema_mcd_database.png")
    plt.tight_layout()
    plt.savefig(path, bbox_inches='tight', dpi=200)
    plt.close()
    print("Database schema généré:", path)

# -------------------------------------------------------------
# 4. SÉCURITÉ & PROTOCOLE CNDP INFOGRAPHIE
# -------------------------------------------------------------
def generate_security_schema():
    fig, ax = plt.subplots(figsize=(11, 4.8), dpi=200)
    ax.set_facecolor('#F8FAFC')
    fig.patch.set_facecolor('#FFFFFF')

    pillars = [
        ("Écrin Cryptographique", "Mots de passe Bcrypt (cost 12)\nSessions sécurisées HTTP-only\nChiffrement données sensibles", "#16375A"),
        ("Étanchéité Multi-Tenant", "Middleware Tenant Resolver\nGlobal Scope Eloquent strict\nZéro porosité inter-campus", "#2E6F9E"),
        ("Conformité CNDP 09-08", "Consentement explicite\nRegistre des traitements\nDroit à l'oubli automatisé", "#D97706"),
        ("Audit & Traçabilité", "Journalisation des accès admin\nDétection des requêtes suspectes\nSauvegardes chiffrées quotidiennes", "#059669")
    ]

    for i, (title, desc, color) in enumerate(pillars):
        x = 0.5 + i * 2.8
        y = 0.8
        rect = patches.FancyBboxPatch((x, y), 2.5, 2.8, boxstyle="round,pad=0.04,rounding_size=0.15",
                                      facecolor='#FFFFFF', edgecolor=color, linewidth=2, zorder=2)
        ax.add_patch(rect)

        # Header banner inside
        h_box = patches.FancyBboxPatch((x, y + 2.0), 2.5, 0.8, boxstyle="round,pad=0.04,rounding_size=0.15",
                                       facecolor=color, edgecolor=color, linewidth=2, zorder=3)
        ax.add_patch(h_box)
        ax.text(x + 1.25, y + 2.4, title, ha='center', va='center', fontsize=10, fontweight='bold', color='#FFFFFF', zorder=4)

        ax.text(x + 1.25, y + 1.0, desc, ha='center', va='center', fontsize=9, color='#334155', zorder=3)

    ax.set_xlim(0, 12)
    ax.set_ylim(0.4, 4.2)
    ax.axis('off')
    plt.title("Architecture de Sécurité Applicative & Souveraineté des Données (CNDP)",
              fontsize=13, fontweight='bold', color='#16375A', pad=15)

    path = os.path.join(output_dir, "security_cndp_schema.png")
    plt.tight_layout()
    plt.savefig(path, bbox_inches='tight', dpi=200)
    plt.close()
    print("Security schema généré:", path)

if __name__ == "__main__":
    generate_insertion_infographic()
    generate_benchmark_chart()
    generate_database_schema()
    generate_security_schema()
    print("Toutes les infographies complémentaires ont été générées avec succès !")
