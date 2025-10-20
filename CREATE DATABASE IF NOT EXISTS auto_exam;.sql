CREATE DATABASE IF NOT EXISTS auto_exam;
USE auto_exam;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    isAdmin BOOLEAN DEFAULT TRUE
);

CREATE TABLE cars (
    id INT PRIMARY KEY AUTO_INCREMENT,
    marque VARCHAR(100) NOT NULL,
    modele VARCHAR(100) NOT NULL,
    annee INT NOT NULL,
    image VARCHAR(255),
    prix DECIMAL(10,2) NOT NULL
);

CREATE TABLE articles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titre VARCHAR(255) NOT NULL,
    contenu TEXT NOT NULL,
    date_publication DATETIME NOT NULL,
    voiture_id INT NULL,
    FOREIGN KEY (voiture_id) REFERENCES cars(id) ON DELETE SET NULL
);

CREATE TABLE offers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titre VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    prix_promo DECIMAL(10,2) NOT NULL,
    date_validite DATE NOT NULL,
    voiture_id INT NOT NULL,
    FOREIGN KEY (voiture_id) REFERENCES cars(id) ON DELETE CASCADE
);

CREATE TABLE contacts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telephone VARCHAR(20) NOT NULL,
    commentaire TEXT NOT NULL,
    voiture_id INT NOT NULL,
    date_creation DATETIME NOT NULL,
    FOREIGN KEY (voiture_id) REFERENCES cars(id) ON DELETE CASCADE
);