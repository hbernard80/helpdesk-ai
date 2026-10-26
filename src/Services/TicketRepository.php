<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class TicketRepository
{
    /**
     * Configure l'accès aux tickets avec une connexion utilisant les résultats associatifs.
     *
     * @param PDO $pdo Connexion MySQL avec PDO::FETCH_ASSOC et exceptions activées.
     */
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Liste les tickets du plus récent au plus ancien, puis par identifiant décroissant.
     *
     * @return list<array{id: int, title: string, description: string, category: string|null, validated_by_human: int, ai_suggestion: string|null, priority: string|null, status: string, created_at: string}> Tickets destinés à la liste principale.
     * @throws \PDOException Si la lecture en base échoue.
     */
    public function findAll(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, title, description, category, validated_by_human,
                    ai_suggestion, priority, status, created_at
             FROM tickets
             ORDER BY created_at DESC, id DESC'
        );

        return $statement->fetchAll();
    }

    /**
     * Crée un ticket avec les valeurs par défaut définies dans le schéma SQL.
     *
     * @param string $title Titre préalablement validé par l'appelant.
     * @param string $description Description du problème.
     * @return int Identifiant du ticket créé.
     * @throws \PDOException Si l'insertion échoue.
     */
    public function create(string $title, string $description): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO tickets (title, description) VALUES (:title, :description)'
        );

        $statement->execute(['title' => $title, 'description' => $description]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Recherche un ticket par son identifiant.
     *
     * @param int $id Identifiant du ticket recherché.
     * @return array{id: int, title: string, description: string, category: string|null, ai_suggestion: string|null, ai_model: string|null, validated_by_human: int, status: string}|null Ticket trouvé, ou null s'il n'existe pas.
     * @throws \PDOException Si la lecture en base échoue.
     */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, description, category,
                    ai_suggestion, ai_model,
                    validated_by_human, status
             FROM tickets
             WHERE id = :id'
        );

        $statement->execute(['id' => $id]);

        $ticket = $statement->fetch(PDO::FETCH_ASSOC);

        return $ticket === false ? null : $ticket;
    }

    /**
     * Enregistre une proposition de catégorie et réinitialise la validation humaine.
     *
     * @param int $id Identifiant du ticket à modifier.
     * @param string $category Catégorie proposée par le modèle.
     * @param string $model Nom du modèle ayant produit la proposition.
     * @return void
     * @throws \PDOException Si la mise à jour échoue.
     */
    public function saveAiSuggestion(int $id, string $category, string $model): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tickets
             SET ai_suggestion = :suggestion,
                 ai_model = :model,
                 validated_by_human = 0
             WHERE id = :id'
        );

        $statement->execute(['id' => $id, 'suggestion' => $category, 'model' => $model]);
    }

    /**
     * Enregistre la catégorie retenue et sa validation si une proposition IA existe.
     *
     * @param int $id Identifiant du ticket à valider.
     * @param string $category Catégorie préalablement validée par l'appelant.
     * @return void
     * @throws \PDOException Si la mise à jour échoue.
     */
    public function validateCategory(int $id, string $category): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tickets
             SET category = :category,
                 validated_by_human = 1
             WHERE id = :id
               AND ai_suggestion IS NOT NULL'
        );

        $statement->execute(['id' => $id, 'category' => $category]);

        error_log(
            'Validation ticket ' . $id .
            ' - catégorie : ' . $category .
            ' - lignes modifiées : ' . $statement->rowCount()
        );
    }
}
