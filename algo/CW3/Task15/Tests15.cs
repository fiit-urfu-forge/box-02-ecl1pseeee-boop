using CW3.Utils;

namespace CW3.Task15;

public class Tests15
{
    public static void Test()
    {
        Console.WriteLine("################ Задача 15. Наибольшая общая подстрока ################\n");

        // Тест 1: пример из условия (выбор лексикографически наименьшей)
        RunTest("ubrashvabracadabra", "calamburashabratha", "abra", "Пример из условия");

        // Тест 2: строки полностью совпадают
        RunTest("abcdefg", "abcdefg", "abcdefg", "Совпадающие строки");

        // Тест 3: общих подстрок нет
        RunTest("abc", "xyz", "", "Нет общих подстрок");

        // Тест 4: одна строка — подстрока другой
        RunTest("aaa", "aa", "aa", "Одна строка — подстрока другой");

        // Тест 5: длинная общая подстрока в середине
        RunTest("banana", "ananas", "anana", "Длинная общая подстрока");

        // Тест 6: две общие подстроки равной длины — берём лексикографически меньшую
        RunTest("catdog", "dogcat", "cat", "Две подстроки равной длины");

        // Тест 7: совпадения только из одного символа
        RunTest("abcdef", "fedcba", "a", "Совпадения длины 1");

        // Тест 8: общие подстроки разнесены по строкам
        RunTest("zzabzzcd", "qqabqqcd", "ab", "Тай-брейк по лексикографике");

        // Тест 9: учёт регистра символов
        RunTest("Hello", "World", "l", "Регистр символов важен");

        // Тест 10: цифры и буквы вместе
        RunTest("X1y2X1y2", "00X1y200", "X1y2", "Цифры и буквы");
    }

    private static void RunTest(string a, string b, string expected, string testName)
    {
        Console.WriteLine($"Тест: {testName}");
        try
        {
            var profileResult = AlgorithmProfiler.Profile("Наибольшая общая подстрока", () =>
                Task15.Solution(a, b));

            string result = profileResult.Result!.FirstOrDefault() ?? "";

            Console.WriteLine($"Входные данные: \"{a}\" / \"{b}\"");
            if (result == expected)
                Console.WriteLine("Тест пройден успешно!");
            else
                Console.WriteLine($"ПРОВАЛЕНО! Ожидалось: \"{expected}\", получено: \"{result}\"");

            Console.WriteLine(profileResult.ToString());
            Console.WriteLine("════════════════════════════════════════\n");
        }
        catch (Exception ex)
        {
            Console.WriteLine($"ERROR: {ex.Message}");
        }
    }
}
